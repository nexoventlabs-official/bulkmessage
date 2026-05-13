import React, { useState } from 'react';
import clsx from 'clsx';
import { Search, Plus, Bell, BellOff, X } from 'lucide-react';
import Avatar from './Avatar.jsx';
import { formatPhone } from '../utils/country';
import { ist } from '../utils/time';
import { getNotifyEnabled, setNotifyEnabled, ensurePermission } from '../utils/notify';

function ContactRow({ contact, selected, onClick }) {
  const displayName = contact.name || contact.profileName || formatPhone(contact.waId);
  const lastAt = contact.lastMessageAt ? ist(contact.lastMessageAt) : null;
  const timeStr = lastAt
    ? (lastAt.isToday?.() ? lastAt.format('h:mm A') : lastAt.format('DD/MM/YY'))
    : '';

  return (
    <div
      onClick={onClick}
      className={clsx(
        'flex items-center gap-3 px-3 py-3 cursor-pointer hover:bg-gray-100 border-b border-gray-100',
        selected && 'bg-wati-panel'
      )}
    >
      <Avatar name={displayName} url={contact.profilePicUrl} size={42} />
      <div className="flex-1 min-w-0">
        <div className="flex justify-between items-start">
          <span className="font-medium text-sm truncate">{displayName}</span>
          <span className="text-[11px] text-wati-muted whitespace-nowrap ml-2">{timeStr}</span>
        </div>
        <div className="text-xs text-wati-muted truncate mt-0.5">
          {contact.lastMessagePreview || 'No messages yet'}
        </div>
      </div>
      {contact.unreadCount > 0 && (
        <span className="bg-wati-primary text-white text-[11px] font-bold rounded-full w-5 h-5 flex items-center justify-center shrink-0">
          {contact.unreadCount > 99 ? '99+' : contact.unreadCount}
        </span>
      )}
    </div>
  );
}

export default function Sidebar({ contacts, selectedId, onSelect, onAdd }) {
  const [search, setSearch] = useState('');
  const [notif, setNotif] = useState(getNotifyEnabled());
  const [addOpen, setAddOpen] = useState(false);
  const [newWaId, setNewWaId] = useState('');
  const [newName, setNewName] = useState('');

  const filtered = contacts.filter(c => {
    if (!search) return true;
    const s = search.toLowerCase();
    return (c.name || '').toLowerCase().includes(s)
      || (c.profileName || '').toLowerCase().includes(s)
      || (c.waId || '').includes(s);
  });

  async function toggleNotif() {
    if (!notif) {
      await ensurePermission();
    }
    const next = !notif;
    setNotifyEnabled(next);
    setNotif(next);
  }

  function handleAdd() {
    const id = newWaId.replace(/\D/g, '');
    if (!id) return;
    onAdd({ waId: id, name: newName });
    setNewWaId('');
    setNewName('');
    setAddOpen(false);
  }

  return (
    <aside className="w-[340px] shrink-0 bg-white border-r border-gray-200 flex flex-col h-full">
      <div className="px-3 py-2 bg-wati-panel border-b border-wati-border flex items-center gap-2">
        <div className="relative flex-1">
          <Search size={16} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-wati-muted" />
          <input
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="Search or start new chat"
            className="w-full pl-8 pr-2 py-2 bg-white rounded-lg text-sm outline-none border border-gray-200 focus:border-wati-primary"
          />
        </div>
        <button onClick={toggleNotif} className="p-2 rounded-full hover:bg-black/5 text-wati-muted" title={notif ? 'Mute' : 'Unmute'}>
          {notif ? <Bell size={18} /> : <BellOff size={18} />}
        </button>
        <button onClick={() => setAddOpen(v => !v)} className="p-2 rounded-full hover:bg-black/5 text-wati-muted" title="Add contact">
          <Plus size={18} />
        </button>
      </div>

      {addOpen && (
        <div className="px-3 py-2 bg-green-50 border-b space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-wati-text">Add Contact</span>
            <button onClick={() => setAddOpen(false)}><X size={14} className="text-wati-muted" /></button>
          </div>
          <input value={newWaId} onChange={e => setNewWaId(e.target.value)} placeholder="WhatsApp number (e.g. 919876543210)" className="w-full px-2 py-1.5 text-sm bg-white rounded border" />
          <input value={newName} onChange={e => setNewName(e.target.value)} placeholder="Name (optional)" className="w-full px-2 py-1.5 text-sm bg-white rounded border" />
          <button onClick={handleAdd} className="px-3 py-1.5 text-xs bg-wati-primary text-white rounded">Add</button>
        </div>
      )}

      <div className="flex-1 overflow-y-auto thin-scroll">
        {filtered.length === 0 ? (
          <div className="text-center text-wati-muted text-sm py-10">No contacts found</div>
        ) : (
          filtered.map(c => (
            <ContactRow
              key={c._id}
              contact={c}
              selected={c._id === selectedId}
              onClick={() => onSelect(c._id)}
            />
          ))
        )}
      </div>
    </aside>
  );
}
