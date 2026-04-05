# Mercado-Neon

[![Framework: Laravel 13](https://img.shields.io/badge/Framework-Laravel_13-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![Microservice: Node.js](https://img.shields.io/badge/Microservice-Node.js-339933?style=flat-square&logo=node.js)](https://nodejs.org)
[![Docker: Ready](https://img.shields.io/badge/Docker-Ready-2496ED?style=flat-square&logo=docker)](https://www.docker.com)

A high-velocity integration platform built to handle RO e-Factura (ANAF SPV) compliance. This project demonstrates a modern polyglot architecture, combining a Laravel 13 monolith with a specialized TypeScript microservice for UBL 2.1 XML generation.

## 🏗 Architecture Overview

The system is designed as a containerized monorepo, ensuring a clean separation of concerns and optimized performance for older hardware through targeted microservices:

- **Primary Backend (Laravel 13):** Manages the business logic, OAuth 2.0 authentication with ANAF, database persistence, and task scheduling for token refreshes.
- **UBL Generation Microservice (Node.js/TypeScript):** A specialized internal service that utilizes the `efactura-anaf-ts-sdk` to transform JSON data into validated UBL 2.1 XML.
- **Infrastructure:** A multi-container Docker stack including Nginx (Web), PHP-FPM (App), MySQL 8 (Database), and Redis (Queue/Cache).

## 🚀 Key Features

- **Automated ANAF OAuth 2.0:** Scheduled tasks handle the complexity of refreshing SPV access tokens.
- **Internal Microservice Communication:** Secure, synchronous HTTP calls between the PHP and Node.js containers via a private Docker bridge network.
- **Regulatory Compliance:** Full support for Romanian e-Factura standards (CIUS-RO) and UBL 2.1 specifications.
- **Headless Optimized:** Designed to run efficiently on Fedora Server 43 with minimal resource overhead.

## 🛠 Tech Stack

- **Languages:** PHP 8.3+, TypeScript 5+
- **Frameworks:** Laravel 13, Express.js
- **DevOps:** Docker, Docker Compose, Portainer, Git
- **Database:** MySQL 8.0 / MariaDB
- **Middleware:** Redis (Queue Management), Nginx

## 🔧 Installation & Setup

### Prerequisites

- Docker & Docker Compose
- A valid ANAF Digital Certificate (for production/sandbox testing)

### 1. Clone & Environment

```bash
git clone https://github.com/your-username/mercado-neon.git
cd mercado-neon
cp backend/.env.example backend/.env
```

### 2. Infrastructure Deployment

Build and start the container stack:

```bash
docker compose up -d --build
```

### 3. Application Initialization

```bash
# Install dependencies
docker compose exec app composer install
docker compose exec efactura-node npm install

# Run migrations
docker compose exec app php artisan migrate
```

## 📂 Project Structure

```text
├── backend/                # Laravel 13 Application
├── efactura-microservice/  # Node.js TypeScript Service
│   ├── src/                # Generation logic
│   └── dist/               # Compiled JavaScript
├── docker/                 # Nginx & PHP runtime configs
├── docker-compose.yml      # Master orchestration
└── README.md
```

## 🛡 Security & Development

- **Sensitive Data:** All credentials (ANAF Client IDs, DB passwords) are managed via `.env` files and are excluded from version control.
- **Internal API:** The UBL microservice is not exposed to the public internet; it is only reachable by the Laravel backend through the internal Docker network.

## 📄 License

This project is open-source software licensed under the [MIT license](LICENSE).
