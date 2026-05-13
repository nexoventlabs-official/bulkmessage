import React, { useCallback, useEffect, useRef, useState } from 'react';
import clsx from 'clsx';
import { MoreVertical, Info, Trash2, X, LayoutTemplate } from 'lucide-react';
import { Messages, Contacts } from '../api/client';
import { socket } from '../api/socket';
import MessageList from './MessageList.jsx';
import MessageInput from './MessageInput.jsx';
import WindowTimer from './WindowTimer.jsx';
import Avatar from './Avatar.jsx';
import { formatPhone } from '../utils/country';

export default function ChatPanel({
  contact,
  onContactUpdate,
  onOpenDetails,
  onOpenTemplates,
  templateToSend,
  onTemplateSent,
}) {
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(false);
  const [replyTo, setReplyTo] = useState(null);
  const [menuOpen, setMenuOpen] = useState(false);
  const bottomRef = useRef(null);
  const menuRef = useRef(null);

  const contactId = contact?._id;

  const scrollToBottom = useCallback(() => {
    setTimeout(() => bottomRef.current?.scrollIntoView({ behavior: 'smooth' }), 60);
  }, []);

  // Load messages
  useEffect(() => {
    if (!contactId) { setMessages([]); return; }
    setLoading(true);
    Messages.list(contactId)
      .then(msgs => { setMessages(msgs); scrollToBottom(); })
      .finally(() => setLoading(false));
  }, [contactId, scrollToBottom]);

  // Mark read
  useEffect(() => {
    if (contactId && contact?.unreadCount > 0) {
      Contacts.markRead(contactId).catch(() => {});
    }
  }, [contactId, contact?.unreadCount]);

  // Real-time socket events
  useEffect(() => {
    const onNew = (msg) => {
      if (String(msg.contact) !== String(contactId)) return;
      setMessages(prev => {
        if (prev.find(m => m._id === msg._id)) return prev;
        return [...prev, msg];
      });
      scrollToBottom();
    };
    const onUpdate = (msg) => {
      setMessages(prev => prev.map(m => m._id === msg._id ? { ...m, ...msg } : m));
    };
    const onDelete = ({ id }) => {
      setMessages(prev => prev.filter(m => m._id !== id));
    };
    const onCleared = ({ contactId: cId }) => {
      if (String(cId) === String(contactId)) setMessages([]);
    };

    socket.on('message:new', onNew);
    socket.on('message:update', onUpdate);
    socket.on('message:delete', onDelete);
    socket.on('chat:cleared', onCleared);
    return () => {
      socket.off('message:new', onNew);
      socket.off('message:update', onUpdate);
      socket.off('message:delete', onDelete);
      socket.off('chat:cleared', onCleared);
    };
  }, [contactId, scrollToBottom]);

  // Auto send template when picked from drawer
  useEffect(() => {
    if (!templateToSend || !contactId) return;
    const t = templateToSend;
    Messages.sendTemplate(contactId, {
      templateName: t.name,
      language: t.language,
      components: [],
    })
      .then(() => onTemplateSent?.())
      .catch(e => {
        alert('Template send failed: ' + (e.response?.data?.error || e.message));
        onTemplateSent?.();
      });
  }, [templateToSend, contactId, onTemplateSent]);

  // Close menu on outside click
  useEffect(() => {
    const handler = (e) => {
      if (menuRef.current && !menuRef.current.contains(e.target)) setMenuOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  async function handleSendText(text, replyWamid) {
    if (!contactId) return;
    try {
      await Messages.sendText(contactId, text, replyWamid);
    } catch (e) {
      const err = e.response?.data;
      if (err?.error === 'WINDOW_CLOSED') {
        alert('24h window closed. Use a template to re-engage.');
      } else {
        alert('Send failed: ' + (err?.error || e.message));
      }
    }
  }

  async function handleSendMedia(data) {
    if (!contactId) return;
    try {
      await Messages.sendMedia(contactId, data);
    } catch (e) {
      alert('Send failed: ' + (e.response?.data?.error || e.message));
    }
  }

  async function handleReact(msg, emoji) {
    if (!contactId) return;
    try {
      await Messages.sendReaction(contactId, msg.wamid, emoji);
    } catch (e) {
      console.error('Reaction failed', e);
    }
  }

  async function handleDelete(msg) {
    if (!confirm('Delete this message from the panel?')) return;
    try {
      await Messages.delete(msg._id);
    } catch (e) {
      alert('Delete failed: ' + (e.response?.data?.error || e.message));
    }
  }

  async function handleClearChat() {
    if (!contactId) return;
    if (!confirm('Clear entire chat history for this contact? This cannot be undone.')) return;
    setMenuOpen(false);
    try {
      await Contacts.clearChat(contactId);
    } catch (e) {
      alert('Clear failed: ' + (e.response?.data?.error || e.message));
    }
  }

  if (!contact) {
    return (
      <div className="flex-1 flex items-center justify-center chat-bg">
        <div className="text-center text-wati-muted">
          <div className="text-5xl mb-4 opacity-30">💬</div>
          <div className="text-lg font-medium">Select a contact to start chatting</div>
          <div className="text-sm mt-1">Your messages will appear here</div>
        </div>
      </div>
    );
  }

  const displayName = contact.name || contact.profileName || formatPhone(contact.waId);

  return (
    <div className="flex-1 flex flex-col h-full">
      {/* Header */}
      <div className="px-4 py-2.5 bg-wati-panel border-b border-wati-border flex items-center gap-3">
        <Avatar name={displayName} url={contact.profilePicUrl} size={40} />
        <div className="flex-1 min-w-0">
          <div className="font-medium text-sm truncate">{displayName}</div>
          <div className="text-xs text-wati-muted">{formatPhone(contact.waId)}</div>
        </div>
        <WindowTimer lastCustomerMessageAt={contact.lastCustomerMessageAt} />
        <button onClick={onOpenDetails} className="p-2 rounded-full hover:bg-black/5 text-wati-muted" title="Contact details"><Info size={20} /></button>
        <div className="relative" ref={menuRef}>
          <button onClick={() => setMenuOpen(v => !v)} className="p-2 rounded-full hover:bg-black/5 text-wati-muted"><MoreVertical size={20} /></button>
          {menuOpen && (
            <div className="absolute right-0 top-10 z-20 bg-white rounded-lg shadow-lg border py-1 min-w-[180px]">
              <button onClick={handleClearChat} className="w-full flex items-center gap-2 px-3 py-2 hover:bg-gray-100 text-sm text-red-600"><Trash2 size={16} /> Clear chat</button>
            </div>
          )}
        </div>
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto thin-scroll chat-bg px-4 py-3">
        {loading ? (
          <div className="text-center text-wati-muted text-sm py-10">Loading messages…</div>
        ) : messages.length === 0 ? (
          <div className="text-center text-wati-muted text-sm py-10">No messages yet. Send the first one!</div>
        ) : (
          <MessageList
            messages={messages}
            onReply={(m) => setReplyTo(m)}
            onDelete={handleDelete}
            onReact={handleReact}
          />
        )}
        <div ref={bottomRef} />
      </div>

      {/* Input */}
      <MessageInput
        contact={contact}
        onSendText={handleSendText}
        onSendMedia={handleSendMedia}
        onOpenTemplates={onOpenTemplates}
        replyTo={replyTo}
        onCancelReply={() => setReplyTo(null)}
      />
    </div>
  );
}
