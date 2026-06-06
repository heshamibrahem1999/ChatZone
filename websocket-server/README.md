# ChatZone WebSocket Server

This is a small Node.js WebSocket relay for local development.

## Install once

```bash
cd websocket-server
npm install
```

## Run

```bash
npm start
```

It runs at:

```text
ws://localhost:8081
```

## What it does

PHP still saves messages/reactions/polls to MySQL.
The WebSocket server only tells other open browsers:

- private chat changed
- group chat changed

Then the browser refreshes the correct chat area with existing PHP endpoints.
