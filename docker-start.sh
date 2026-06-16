#!/bin/bash
docker compose up --build -d
echo ""
echo "  Frontend: http://localhost:3000"
echo "  Backend:  http://localhost:8080/api/v1"
echo "  Login:    admin@interlinked.io / admin123"
echo ""
echo "  docker compose down   para parar"
