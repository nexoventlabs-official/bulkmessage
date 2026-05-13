import { io } from 'socket.io-client';

const BASE = import.meta.env.VITE_API_URL || '';

export const socket = io(BASE, {
  transports: ['websocket', 'polling'],
  autoConnect: true,
});

// Call this after user login to join user-specific room
export function joinUserRoom(userId) {
  if (userId) {
    socket.emit('join', { userId });
  }
}
