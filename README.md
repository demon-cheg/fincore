<div align="center">

# FinCore

### Transaction-safe financial backend built with Laravel, MySQL, Redis and RabbitMQ

[![CI](https://github.com/demon-cheg/fincore/actions/workflows/ci.yml/badge.svg)](https://github.com/demon-cheg/fincore/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?logo=mysql&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-8-DC382D?logo=redis&logoColor=white)
![RabbitMQ](https://img.shields.io/badge/RabbitMQ-4-FF6600?logo=rabbitmq&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)

**FinCore is a backend portfolio project focused on concurrency, consistency, idempotency and reliable event delivery — not CRUD for CRUD's sake.**

</div>

---

## Overview

FinCore models a compact financial transaction backend where the interesting problems are correctness under retries, concurrent requests and partial infrastructure failures.

The project demonstrates:

- transaction-safe money transfers
- pessimistic row locking
- deterministic lock ordering
- idempotent transfer requests
- SHA-256 request fingerprints
- Redis distributed locking
- transactional outbox pattern
- RabbitMQ publisher confirms
- durable queues
- delayed retries
- dead-letter queues
- idempotent consumers
- PHPUnit integration testing
- PHPStan / Larastan static analysis
- Docker-based infrastructure
- GitHub Actions CI

> This is a technical portfolio project, not production banking software.

---

## Architecture

```mermaid
flowchart LR
    Client[API Client]
    API[Laravel API]
    Redis[(Redis)]
    MySQL[(MySQL 8.4)]
    Publisher[Outbox Publisher]
    Exchange{{RabbitMQ Topic Exchange}}
    AuditQ[[fincore.audit]]
    RetryQ[[fincore.audit.retry]]
    DLQ[[fincore.audit.dlq]]
    Consumer[Audit Consumer]

    Client -->|Sanctum + Idempotency-Key| API
    API --> Redis
    API -->|DB transaction + FOR UPDATE| MySQL
    MySQL -->|pending outbox event| Publisher
    Publisher -->|publisher confirm| Exchange
    Exchange --> AuditQ
    AuditQ --> Consumer
    Consumer -->|audit event| MySQL
    Consumer -->|failure| RetryQ
    RetryQ -->|TTL expires| Exchange
    Consumer -->|retry limit exceeded| DLQ