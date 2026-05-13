let _io = null;

function setIO(io) { _io = io; }

function emit(event, data) {
  if (_io) _io.emit(event, data);
}

// Emit to a specific user's room
function emitToUser(userId, event, data) {
  if (_io) _io.to(`user:${userId}`).emit(event, data);
}

module.exports = { setIO, emit, emitToUser };
