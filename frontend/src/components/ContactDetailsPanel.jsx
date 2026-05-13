import React, { useMemo, useState, useEffect } from 'react';
import clsx from 'clsx';
import { Phone, Tag, Calendar, StickyNote, Hash, X, Clock, Pencil, Check } from 'lucide-react';
import { Contacts, Messages } from '../api/client';
import { socket } from '../api/socket';
import { resolveCountry, formatPhone } from '../utils/country';
import { ist, windowState, formatCountdown } from '../utils/time';
import Avatar from './Avatar.jsx';
import NotesDialog from './NotesDialog.jsx';

function Field({ icon: Icon, label, children, border = true }) {
  if (!children) return null;
  return (
    <div className={clsx("py-3 px-5 flex gap-4", border && "border-b border-gray-100")}>
      {Icon && <div className="mt-0.5 text-wati-muted/70"><Icon size={20} strokeWidth={1.5} /></div>}
      <div className="flex-1 min-w-0">
        <div className="text-[15px] text-wati-text break-words leading-tight">{children}</div>
        <div className="text-[13px] text-wati-muted mt-1">{label}</div>
      </div>
    </div>
  );
}

export default function ContactDetailsPanel({ contact, onClose, onContactUpdate }) {
  const [notesOpen, setNotesOpen] = useState(false);
  const [editingName, setEditingName] = useState(false);
  const [nameDraft, setNameDraft] = useState('');
  const [savingName, setSavingName] = useState(false);
  const [, tick] = useState(0);

  useEffect(() => {
    const t = setInterval(() => tick(v => v + 1), 1000);
    return () => clearInterval(t);
  }, []);

  useEffect(() => {
    setEditingName(false);
    setNameDraft(contact?.name || contact?.profileName || '');
  }, [contact?._id]);

  async function saveName() {
    if (!contact?._id) return;
    const trimmed = (nameDraft || '').trim();
    if (trimmed === (contact.name || '')) { setEditingName(false); return; }
    setSavingName(true);
    try {
      const updated = await Contacts.update(contact._id, { name: trimmed });
      onContactUpdate?.(updated);
      setEditingName(false);
    } catch (e) {
      alert('Failed: ' + (e?.response?.data?.error || e.message));
    } finally {
      setSavingName(false);
    }
  }

  const country = useMemo(() => resolveCountry(contact?.waId), [contact?.waId]);
  const ws = windowState(contact?.lastCustomerMessageAt);

  if (!contact) return null;

  const displayName = contact.name || contact.profileName || `+${contact.waId}`;
  const noteEntries = Array.isArray(contact.notes) ? [...contact.notes] : [];
  noteEntries.sort((a, b) => new Date(b.createdAt || 0) - new Date(a.createdAt || 0));
  const latestNote = noteEntries[0] || null;

  return (
    <aside className="w-[22rem] shrink-0 bg-[#f0f2f5] border-l border-gray-200 flex flex-col h-full z-10">
      <div className="bg-white shadow-sm pb-5 relative shrink-0">
        <div className="relative h-20 bg-gradient-to-r from-wati-primary to-wati-primaryDark">
          {onClose && (
            <button onClick={onClose} className="absolute top-3 right-3 p-2 rounded-full bg-black/40 hover:bg-black/60 text-white transition-colors" title="Close">
              <X size={18} />
            </button>
          )}
        </div>
        <div className="flex flex-col items-center -mt-10 relative z-10 px-4">
          <Avatar name={displayName} url={contact.profilePicUrl} size={80} className="ring-4 ring-white shadow-sm" />
          <div className="mt-3 text-[18px] font-medium text-wati-text flex items-center gap-2">
            {editingName ? (
              <div className="flex items-center gap-1.5">
                <input autoFocus value={nameDraft} onChange={(e) => setNameDraft(e.target.value)}
                  onKeyDown={(e) => { if (e.key === 'Enter') saveName(); else if (e.key === 'Escape') setEditingName(false); }}
                  disabled={savingName} placeholder="Enter name"
                  className="border border-gray-300 rounded px-2 py-0.5 text-[16px] font-medium outline-none focus:border-wati-primary max-w-[12rem]" />
                <button onClick={saveName} disabled={savingName} className="p-1.5 rounded-full bg-wati-primary text-white hover:bg-wati-primaryDark disabled:opacity-50"><Check size={14} /></button>
                <button onClick={() => { setNameDraft(contact.name || contact.profileName || ''); setEditingName(false); }} className="p-1.5 rounded-full hover:bg-gray-200 text-wati-muted"><X size={14} /></button>
              </div>
            ) : (
              <>
                <span className="truncate">{displayName}</span>
                <button onClick={() => { setNameDraft(contact.name || contact.profileName || ''); setEditingName(true); }} className="p-1 rounded-full hover:bg-gray-100 text-wati-muted" title="Edit name"><Pencil size={14} /></button>
              </>
            )}
          </div>
          <div className="text-[14px] text-wati-muted mt-1 flex items-center gap-1.5">
            <a href={`tel:+${String(contact.waId).replace(/\D/g, '')}`} className="hover:underline">{formatPhone(contact.waId)}</a>
            <span className="text-gray-300">•</span>
            <span>{country.flag} {country.name}</span>
          </div>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto thin-scroll pb-6">
        <div className="bg-white shadow-sm mt-2 py-1">
          {contact.profileName && contact.profileName !== contact.name && (
            <Field icon={Tag} label="WhatsApp profile name">{contact.profileName}</Field>
          )}
          <Field icon={Calendar} label="Contact created">
            {contact.createdAt ? ist(contact.createdAt).format('DD MMM YYYY, h:mm A') : '—'}
          </Field>
          <Field icon={Clock} label="24h customer-service window" border={false}>
            {ws.expired ? (
              <span className="text-red-600 font-medium">Window closed — templates only</span>
            ) : !contact.lastCustomerMessageAt ? (
              <span className="text-wati-muted italic text-[14px]">No customer message yet</span>
            ) : (
              <div className="flex items-center gap-2">
                <span className={clsx('font-mono font-medium px-2 py-0.5 rounded text-[13px]', ws.danger ? 'bg-red-50 text-red-700 animate-pulse' : 'bg-green-50 text-green-700')}>
                  {formatCountdown(ws.remainingMs)}
                </span>
                <span className="text-[12px] text-wati-muted">remaining</span>
              </div>
            )}
          </Field>
        </div>

        <div className="bg-white shadow-sm mt-2 px-5 py-4">
          <div className="flex items-center justify-between text-[14px] text-wati-muted font-medium mb-3">
            <div className="flex items-center gap-2"><StickyNote size={18} strokeWidth={1.5} /> Notes {noteEntries.length > 0 && `(${noteEntries.length})`}</div>
            <button onClick={() => setNotesOpen(true)} className="text-wati-primary hover:underline text-[13px] font-normal">View all</button>
          </div>
          <button onClick={() => setNotesOpen(true)} className="w-full text-left outline-none">
            {latestNote ? (
              <div className="bg-[#fff9c4] border border-[#f5eb9d] rounded-lg p-3 hover:bg-[#fff59d] transition-colors shadow-sm">
                <div className="text-yellow-900 text-[14px] whitespace-pre-wrap break-words line-clamp-3">{latestNote.text}</div>
                <div className="text-[11px] text-yellow-700/80 mt-2 font-medium">
                  {latestNote.createdAt ? ist(latestNote.createdAt).format('DD MMM YYYY, h:mm A') : ''}
                </div>
              </div>
            ) : (
              <div className="text-[13px] text-wati-muted italic border-2 border-dashed border-gray-200 rounded-lg p-3 text-center hover:bg-gray-50">No notes — click to add</div>
            )}
          </button>
        </div>

        <div className="mt-4 text-center">
          <div className="text-[11px] text-wati-muted flex justify-center items-center gap-1.5">
            <Hash size={12} /> ID: <code className="bg-black/5 px-1 rounded">{contact._id}</code>
          </div>
        </div>
      </div>

      {notesOpen && <NotesDialog contact={contact} onClose={() => setNotesOpen(false)} onContactUpdate={onContactUpdate} />}
    </aside>
  );
}
