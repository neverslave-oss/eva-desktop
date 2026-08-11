# Feature: API Client & Docker Manager

## Objective
Two low-level subsystems: an HTTP client layer for kernel-evolving's 60+ endpoints, and a Docker CLI wrapper for container lifecycle management.

## Dependencies
- kernel-evolving (for API client)
- Docker installed (for Docker manager)

## Stack
PHP (Laravel HTTP client + Symfony Process for Docker CLI)

## Expected output
### API Client
- HTTP client for all kernel-evolving endpoints:
  - Chat: POST /message, GET /message/stream
  - Dashboard: GET /evolution/state, GET /evolution/stream, GET /evolution/dashboard
  - Memory: GET /memory/files, GET/PUT/DELETE /memory/file, POST /memory/file/rename, POST /memory/file/new
  - Workspace: GET /workspace/tree
  - SQLite: GET /sqlite/tables, GET /sqlite/table, etc.
  - Skills: GET /skills, POST to run
  - Routines: GET /routines, POST to run
  - Provider: GET /provider, POST /provider/set, GET /provider/models
  - Replicas: GET /replica/active, POST /replica/spawn
- SSE streaming support for chat and evolution
- File uploads (voice, image, document)
- Error handling with retry

### Docker Manager
- Check Docker installation and daemon status
- Install instructions per OS
- Pull kernel-evolving image with progress
- Start container with config
- Stop/restart container
- Stream container logs
- Resource monitoring (docker stats)

## Status
[ ] Not started
