# ==========================================
# Stage 1: Builder
# ==========================================
FROM node:20-alpine AS builder

WORKDIR /usr/src/app

# Copy dependency manifests first to leverage Docker layer caching
COPY package*.json ./

# Install ALL dependencies deterministically (requires package-lock.json)
RUN npm ci

# Copy configuration and source code
COPY tsconfig.json ./
COPY src/ ./src/

# Compile TypeScript into JavaScript (outputs to /dist folder)
RUN npx tsc

# ==========================================
# Stage 2: Production
# ==========================================
FROM node:20-alpine

# Enforce production mode for Express and Node
ENV NODE_ENV=production

WORKDIR /usr/src/app

# Copy dependency manifests again for the production stage
COPY package*.json ./

# Install ONLY production dependencies deterministically (requires package-lock.json)
RUN npm ci --omit=dev

# Copy the compiled JavaScript files from the "builder" stage
COPY --from=builder /usr/src/app/dist ./dist

RUN chown -R node:node /usr/src/app

USER node

# Expose the internal port (3000)
EXPOSE 3000

# Start the application using the compiled JavaScript
CMD ["node", "dist/server.js"]