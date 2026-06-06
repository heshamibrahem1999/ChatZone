const WebSocket = require('ws');

const PORT = Number(process.env.CHATZONE_WS_PORT || 8081);
const wss = new WebSocket.Server({ port: PORT });

function safeParse(value) {
  try { return JSON.parse(value); } catch (e) { return null; }
}

function send(ws, packet) {
  if (ws.readyState === WebSocket.OPEN) {
    ws.send(JSON.stringify(packet));
  }
}

function hasRoom(client, scope, id) {
  if (!client.meta || !id) return false;
  if (scope === 'private') return client.meta.privateRooms.has(String(id));
  if (scope === 'group') return client.meta.groupRooms.has(String(id));
  return false;
}

function broadcastToRoom(sender, packet) {
  const scope = packet.scope;
  const roomId = scope === 'private' ? String(packet.friendshipId || '') : String(packet.groupId || '');
  let count = 0;

  wss.clients.forEach((client) => {
    if (client === sender || client.readyState !== WebSocket.OPEN) return;

    
    if (hasRoom(client, scope, roomId)) {
      send(client, packet);
      count += 1;
      return;
    }

    
    if (scope === 'private' && client.meta && client.meta.pageType === 'private') {
      send(client, packet);
      count += 1;
    }
  });

  return count;
}

wss.on('connection', (ws) => {
  ws.isAlive = true;
  ws.meta = { pageType: '', privateRooms: new Set(), groupRooms: new Set() };
  send(ws, { type: 'hello', message: 'ChatZone WS connected' });

  ws.on('pong', () => { ws.isAlive = true; });

  ws.on('message', (raw) => {
    const packet = safeParse(raw.toString());
    if (!packet || !packet.type) return;

    if (packet.type === 'join') {
      if (packet.scope === 'private' && packet.friendshipId) {
        ws.meta.pageType = 'private';
        ws.meta.privateRooms.add(String(packet.friendshipId));
      }
      if (packet.scope === 'group' && packet.groupId) {
        ws.meta.pageType = 'group';
        ws.meta.groupRooms.add(String(packet.groupId));
      }
      send(ws, { type: 'joined', scope: packet.scope, friendshipId: packet.friendshipId, groupId: packet.groupId });
      return;
    }

    if (packet.type === 'presence_changed') {
      wss.clients.forEach((client) => {
        if (client === ws || client.readyState !== WebSocket.OPEN) return;
        send(client, packet);
      });
      return;
    }

    if (packet.type === 'notify' || packet.type === 'typing' || packet.type === 'typing_stop') {
      broadcastToRoom(ws, packet);
      return;
    }
  });
});


setInterval(() => {
  wss.clients.forEach((ws) => {
    if (ws.isAlive === false) {
      try { ws.terminate(); } catch (e) {}
      return;
    }
    ws.isAlive = false;
    try { ws.ping(); } catch (e) {}
  });
}, 30000);

console.log(`ChatZone WebSocket server running on ws://localhost:${PORT}`);
