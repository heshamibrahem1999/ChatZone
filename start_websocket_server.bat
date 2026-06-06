@echo off
cd /d "%~dp0websocket-server"
echo Starting ChatZone WebSocket server on ws://localhost:8081
echo Keep this window open while testing real-time chat.
npm start
pause
