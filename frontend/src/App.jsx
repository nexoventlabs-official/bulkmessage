import React, { useState, useEffect, useCallback } from 'react';
import { Auth, Contacts as ContactsAPI } from './api/client';
import { socket, joinUserRoom } from './api/socket';
import Sidebar from './components/Sidebar.jsx';
import ChatPanel from './components/ChatPanel.jsx';
import ContactDetailsPanel from './components/ContactDetailsPanel.jsx';
import TemplatesDrawer from './components/TemplatesDrawer.jsx';
import { playChime, showMessageNotification, getNotifyEnabled } from './utils/notify';
import UserLogin from './UserLogin.jsx';

const SELECTED_KEY = 'bc:selectedContact';

export default function App() {
  const [user, setUser] = useState(null);
  const [checking, setChecking] = useState(true);
  const [contacts, setContacts] = useState([]);
  const [selectedId, setSelectedId] = useState(() => localStorage.getItem(SELECTED_KEY) || null);
  const [showDetails, setShowDetails] = useState(false);
  const [showTemplates, setShowTemplates] = useState(false);
  const [templateToSend, setTemplateToSend] = useState(null);

  // Check token on mount
  useEffect(() => {
    const token = localStorage.getItem('bc:userToken');
    if (!token) { setChecking(false); return; }
    Auth.me()
      .then(r => { setUser(r.user); joinUserRoom(r.user.id); })
      .catch(() => { localStorage.removeItem('bc:userToken'); })
      .finally(() => setChecking(false));
  }, []);

  // Load contacts
  const loadContacts = useCallback(async () => {
    if (!user) return;
    try {
      const list = await ContactsAPI.list();
      setContacts(list);
    } catch (e) {
      console.error('Failed to load contacts', e);
    }
  }, [user]);

  useEffect(() => { loadContacts(); }, [loadContacts]);

  // Socket events for contacts
  useEffect(() => {
    if (!user) return;
    const onUpsert = (c) => {
      setContacts(prev => {
        const idx = prev.findIndex(x => x._id === c._id);
        if (idx === -1) return [c, ...prev];
        const copy = prev.slice();
        copy[idx] = c;
        return copy.sort((a, b) => new Date(b.lastMessageAt || 0) - new Date(a.lastMessageAt || 0));
      });
    };
    socket.on('contact:upsert', onUpsert);
    return () => socket.off('contact:upsert', onUpsert);
  }, [user]);

  // Notification chime on new inbound
  useEffect(() => {
    if (!user) return;
    const onNew = (msg) => {
      if (msg.direction !== 'inbound') return;
      if (getNotifyEnabled()) {
        playChime();
        const c = contacts.find(x => x._id === msg.contact);
        showMessageNotification({
          contactId: msg.contact,
          title: c?.name || c?.profileName || `+${msg.waId}`,
          body: msg.text || msg.caption || `[${msg.type}]`,
          onClick: () => {
            setSelectedId(msg.contact);
            localStorage.setItem(SELECTED_KEY, msg.contact);
          },
        });
      }
    };
    socket.on('message:new', onNew);
    return () => socket.off('message:new', onNew);
  }, [user, contacts]);

  function handleLogin(userData, token) {
    localStorage.setItem('bc:userToken', token);
    setUser(userData);
    joinUserRoom(userData.id);
  }

  function handleLogout() {
    localStorage.removeItem('bc:userToken');
    localStorage.removeItem(SELECTED_KEY);
    setUser(null);
    setContacts([]);
    setSelectedId(null);
  }

  function selectContact(id) {
    setSelectedId(id);
    localStorage.setItem(SELECTED_KEY, id);
    setShowDetails(false);
  }

  async function addContact(data) {
    try {
      const c = await ContactsAPI.create(data);
      setContacts(prev => {
        if (prev.find(x => x._id === c._id)) return prev;
        return [c, ...prev];
      });
      selectContact(c._id);
    } catch (e) {
      alert('Failed to add contact: ' + (e.response?.data?.error || e.message));
    }
  }

  function handleContactUpdate(updated) {
    setContacts(prev => prev.map(c => c._id === updated._id ? updated : c));
  }

  if (checking) {
    return <div className="h-full flex items-center justify-center bg-wati-panel text-wati-muted">Loading…</div>;
  }

  if (!user) {
    return <UserLogin onLogin={handleLogin} />;
  }

  const selected = contacts.find(c => c._id === selectedId) || null;

  return (
    <div className="h-full flex bg-wati-panel">
      <Sidebar
        contacts={contacts}
        selectedId={selectedId}
        onSelect={selectContact}
        onAdd={addContact}
      />

      <ChatPanel
        contact={selected}
        onContactUpdate={handleContactUpdate}
        onOpenDetails={() => setShowDetails(true)}
        onOpenTemplates={() => setShowTemplates(true)}
        templateToSend={templateToSend}
        onTemplateSent={() => setTemplateToSend(null)}
      />

      {showDetails && selected && (
        <ContactDetailsPanel
          contact={selected}
          onClose={() => setShowDetails(false)}
          onContactUpdate={handleContactUpdate}
        />
      )}

      {showTemplates && (
        <TemplatesDrawer
          onClose={() => setShowTemplates(false)}
          onPick={(t) => { setTemplateToSend(t); setShowTemplates(false); }}
        />
      )}

      {/* Logout button - top-left corner on sidebar */}
      <div className="fixed bottom-4 left-4 z-30">
        <button
          onClick={handleLogout}
          className="px-3 py-1.5 text-xs bg-gray-700 text-white rounded shadow hover:bg-gray-800"
        >
          Logout ({user.username})
        </button>
      </div>
    </div>
  );
}
