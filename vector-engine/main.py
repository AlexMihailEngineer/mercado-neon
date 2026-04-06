from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import faiss
import numpy as np
import os
import pickle
import threading

app = FastAPI(
    title="Mercado Vector Engine",
    description="FAISS-backed semantic search microservice for product matching."
)

# Configuration
INDEX_PATH = "/app/data/products.index"
MAP_PATH = "/app/data/id_map.pkl"
DIMENSION = 768  # This must exactly match the output of nomic-embed-text

index_lock = threading.Lock()


def _persist_index_atomic(current_index: faiss.Index) -> None:
    dir_path = os.path.dirname(INDEX_PATH)
    tmp_path = os.path.join(dir_path, ".products.index.tmp")
    faiss.write_index(current_index, tmp_path)
    os.replace(tmp_path, INDEX_PATH)

def _create_empty_index() -> faiss.Index:
    # L2 distance (Euclidean) is standard for dense embeddings
    base = faiss.IndexFlatL2(DIMENSION)
    return faiss.IndexIDMap2(base)


def _load_or_migrate_index() -> faiss.Index:
    """Load the persisted FAISS index.

    Supports migrating from the legacy format:
    - products.index as IndexFlatL2
    - id_map.pkl mapping internal_id -> product_id

    New format stores product_id directly as the FAISS vector id using IndexIDMap2.
    """
    if not os.path.exists(INDEX_PATH):
        print("No persisted index found. Created new IndexIDMap2 FlatL2 index.")
        return _create_empty_index()

    loaded = faiss.read_index(INDEX_PATH)

    # If the loaded index already supports IDs, keep it.
    # (IndexIDMap2 has id_map inside and returns vector ids from search)
    if isinstance(loaded, faiss.IndexIDMap):
        print(f"Loaded existing ID-mapped index with {loaded.ntotal} vectors.")
        return loaded

    # Otherwise, attempt legacy migration.
    if os.path.exists(MAP_PATH):
        try:
            with open(MAP_PATH, "rb") as f:
                legacy_map = pickle.load(f)

            new_index = _create_empty_index()

            if loaded.ntotal > 0:
                # Reconstruct vectors from legacy index and add with product_id ids.
                internal_ids = list(range(loaded.ntotal))
                vectors = np.vstack([loaded.reconstruct(i) for i in internal_ids]).astype(np.float32)
                ids = np.array(
                    [int(legacy_map[i]) for i in internal_ids if i in legacy_map],
                    dtype=np.int64,
                )

                if len(ids) != vectors.shape[0]:
                    # Filter vectors to those that have a mapping.
                    keep = [i for i in internal_ids if i in legacy_map]
                    vectors = np.vstack([loaded.reconstruct(i) for i in keep]).astype(np.float32)
                    ids = np.array([int(legacy_map[i]) for i in keep], dtype=np.int64)

                new_index.add_with_ids(vectors, ids)

            faiss.write_index(new_index, INDEX_PATH)
            print(f"Migrated legacy index ({loaded.ntotal} vectors) to ID-mapped index ({new_index.ntotal} vectors).")
            return new_index
        except Exception as e:
            print(f"Failed to migrate legacy index. Starting fresh. Reason: {e}")

    print("Loaded legacy index without id map. Starting fresh ID-mapped index.")
    return _create_empty_index()


# Initialize FAISS Memory
index = _load_or_migrate_index()

# Pydantic Schemas for Strict Request Validation
class UpsertRequest(BaseModel):
    product_id: int
    vector: list[float]

class SearchRequest(BaseModel):
    vector: list[float]
    top_k: int = 5

@app.post("/upsert")
async def upsert(request: UpsertRequest):
    # Validate embedding dimension
    if len(request.vector) != DIMENSION:
        raise HTTPException(
            status_code=400, 
            detail=f"Expected vector dimension {DIMENSION}, got {len(request.vector)}"
        )

    # Convert list to a NumPy array of float32 (FAISS strict requirement)
    vec = np.array([request.vector], dtype=np.float32)

    with index_lock:
        # True upsert: remove existing vector id if present, then add with product_id as the vector id
        # (IndexIDMap2 supports remove_ids)
        remove_ids = np.array([request.product_id], dtype=np.int64)
        try:
            index.remove_ids(remove_ids)
        except Exception:
            # Some FAISS builds may raise when removing non-existent ids; safe to ignore.
            pass

        index.add_with_ids(vec, remove_ids)

        # Persist state to disk (atomic replace to avoid corruption on partial writes)
        _persist_index_atomic(index)
        
    return {
        "status": "success", 
        "message": f"Product {request.product_id} indexed.",
        "total_vectors": index.ntotal
    }

@app.post("/search")
async def search(request: SearchRequest):
    with index_lock:
        if index.ntotal == 0:
            return {"results": []}

    if len(request.vector) != DIMENSION:
        raise HTTPException(
            status_code=400, 
            detail=f"Expected vector dimension {DIMENSION}, got {len(request.vector)}"
        )
        
    # Format query vector
    query_vec = np.array([request.vector], dtype=np.float32)
    
    # FAISS search returns distances (L2 scores) and the vector IDs
    # distances are squared Euclidean distances (lower is better/closer)
    with index_lock:
        distances, indices = index.search(query_vec, request.top_k)
    
    results = []
    # indices[0] contains the top_k vector IDs (we store product_id as the FAISS id)
    for i, product_id in enumerate(indices[0]):
        # FAISS returns -1 if it can't find enough neighbors
        if int(product_id) != -1:
            results.append({
                "product_id": int(product_id),
                "l2_distance": float(distances[0][i])
            })
            
    return {"results": results}

@app.get("/health")
async def health():
    return {
        "status": "healthy",
        "vector_count": index.ntotal,
        "dimension": DIMENSION
    }