#!/bin/bash
docker compose up --build -d
echo ""
echo "  Frontend: http://localhost:3000"
echo "  API (via frontend): http://localhost:3000/api/v1"
echo "  Login:    admin@interlinked.io / admin123"
echo ""
echo "  docker compose down   para parar"
