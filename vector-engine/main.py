from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import faiss
import numpy as np
import os
import pickle

app = FastAPI(
    title="Mercado Vector Engine",
    description="FAISS-backed semantic search microservice for product matching."
)

# Configuration
INDEX_PATH = "/app/data/products.index"
MAP_PATH = "/app/data/id_map.pkl"
DIMENSION = 768  # This must exactly match the output of nomic-embed-text

# Initialize FAISS Memory
# FAISS tracks vectors by a sequential internal ID (0, 1, 2...). 
# We need id_map to link FAISS ID -> Laravel MySQL ID.
if os.path.exists(INDEX_PATH) and os.path.exists(MAP_PATH):
    index = faiss.read_index(INDEX_PATH)
    with open(MAP_PATH, "rb") as f:
        id_map = pickle.load(f)
    print(f"Loaded existing index with {index.ntotal} vectors.")
else:
    # L2 distance (Euclidean) is standard for dense embeddings
    index = faiss.IndexFlatL2(DIMENSION)
    id_map = {}
    print("Created new FAISS FlatL2 index.")

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
    
    # Add to FAISS index
    index.add(vec)
    
    # Map the newly added position (ntotal - 1) to the Laravel Product ID
    faiss_id = index.ntotal - 1
    id_map[faiss_id] = request.product_id
    
    # Persist state to disk
    faiss.write_index(index, INDEX_PATH)
    with open(MAP_PATH, "wb") as f:
        pickle.dump(id_map, f)
        
    return {
        "status": "success", 
        "message": f"Product {request.product_id} indexed.",
        "total_vectors": index.ntotal
    }

@app.post("/search")
async def search(request: SearchRequest):
    if index.ntotal == 0:
        return {"results": []}

    if len(request.vector) != DIMENSION:
        raise HTTPException(
            status_code=400, 
            detail=f"Expected vector dimension {DIMENSION}, got {len(request.vector)}"
        )
        
    # Format query vector
    query_vec = np.array([request.vector], dtype=np.float32)
    
    # FAISS search returns distances (L2 scores) and the internal indices
    # distances are squared Euclidean distances (lower is better/closer)
    distances, indices = index.search(query_vec, request.top_k)
    
    results = []
    # indices[0] contains the top_k internal IDs
    for i, internal_id in enumerate(indices[0]):
        # FAISS returns -1 if it can't find enough neighbors (e.g., asking for top 5 but only having 3 items)
        if internal_id != -1 and internal_id in id_map:
            results.append({
                "product_id": id_map[internal_id],
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