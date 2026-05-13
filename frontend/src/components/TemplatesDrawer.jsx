import React, { useEffect, useState } from 'react';
import clsx from 'clsx';
import { X, Plus, RefreshCw, Trash2, CheckCircle2, XCircle, Clock, ExternalLink, Phone, MessageSquare, FileText, Eye, EyeOff, Send } from 'lucide-react';
import { Templates } from '../api/client';
import { socket } from '../api/socket';
import TemplateEditor from './TemplateEditor.jsx';

const STATUS_BADGE = {
  APPROVED: { icon: <CheckCircle2 size={14} />, cls: 'bg-green-100 text-green-700 border-green-300' },
  PENDING: { icon: <Clock size={14} />, cls: 'bg-yellow-100 text-yellow-700 border-yellow-300' },
  REJECTED: { icon: <XCircle size={14} />, cls: 'bg-red-100 text-red-700 border-red-300' },
  DRAFT: { icon: <Clock size={14} />, cls: 'bg-gray-100 text-gray-700 border-gray-300' },
  PAUSED: { icon: <Clock size={14} />, cls: 'bg-orange-100 text-orange-700 border-orange-300' },
  DISABLED: { icon: <XCircle size={14} />, cls: 'bg-red-100 text-red-700 border-red-300' },
};

function TemplateSkeleton() {
  return (
    <div className="border border-gray-200 rounded-lg bg-white p-3 animate-pulse flex items-center gap-3">
      <div className="w-4 h-4 rounded bg-gray-200 shrink-0"></div>
      <div className="flex-1"><div className="h-3.5 bg-gray-200 rounded w-1/3 mb-1.5"></div><div className="h-2 bg-gray-200 rounded w-1/4"></div></div>
      <div className="w-[80px] h-5 rounded bg-gray-200 shrink-0"></div>
    </div>
  );
}

function TemplatePreview({ t }) {
  const header = t.header || {};
  const buttons = Array.isArray(t.buttons) ? t.buttons : [];
  function btnIcon(type) {
    if (type === 'URL') return <ExternalLink size={13} />;
    if (type === 'PHONE_NUMBER') return <Phone size={13} />;
    return <MessageSquare size={13} />;
  }
  return (
    <div className="mt-2 rounded-lg p-2.5 bg-wati-bg border border-black/5">
      <div className="rounded-md overflow-hidden bg-white shadow-sm max-w-full">
        {header.type === 'IMAGE' && header.mediaUrl && <img src={header.mediaUrl} alt="" className="w-full max-h-[160px] object-cover" />}
        {header.type === 'VIDEO' && header.mediaUrl && <video src={header.mediaUrl} controls className="w-full max-h-[180px]" />}
        {header.type === 'DOCUMENT' && header.mediaUrl && (
          <a href={header.mediaUrl} target="_blank" rel="noreferrer" className="flex items-center gap-2 bg-red-50 px-3 py-2 hover:bg-red-100 border-b border-black/5">
            <FileText size={18} className="text-red-600 shrink-0" /><span className="text-xs font-medium truncate">Document</span>
          </a>
        )}
        {header.type === 'TEXT' && header.text && <div className="px-3 pt-2 text-sm font-semibold break-words">{header.text}</div>}
        {t.body && <div className="px-3 py-2 whitespace-pre-wrap text-sm text-wati-text break-words">{t.body}</div>}
        {t.footer && <div className="px-3 pb-2 text-[11px] text-wati-muted break-words">{t.footer}</div>}
      </div>
      {buttons.length > 0 && (
        <div className="mt-1.5 space-y-1">
          {buttons.map((b, i) => (
            <div key={i} className="flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium text-wati-primary bg-white rounded-md shadow-sm">
              {btnIcon(b.type)} <span className="truncate">{b.text || 'Button'}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

export default function TemplatesDrawer({ onClose, onPick }) {
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(false);
  const [editing, setEditing] = useState(false);
  const [busyId, setBusyId] = useState(null);
  const [toast, setToast] = useState(null);
  const [expandedId, setExpandedId] = useState(null);
  const [pickedId, setPickedId] = useState(null);

  const picked = list.find(t => t._id === pickedId) || null;

  const load = async () => { const data = await Templates.list(); setList(data); };
  const sync = async () => { setLoading(true); try { await Templates.sync(); await load(); } finally { setLoading(false); } };
  function showToast(type, msg) { setToast({ type, msg }); setTimeout(() => setToast(null), 5000); }
  function extractError(e) { return e?.response?.data?.error || e?.message || 'Unknown error'; }

  useEffect(() => { load(); sync(); }, []);

  useEffect(() => {
    const onUpd = (t) => setList(prev => { const idx = prev.findIndex(x => x._id === t._id); if (idx === -1) return [t, ...prev]; const c = prev.slice(); c[idx] = t; return c; });
    const onDel = ({ id }) => setList(prev => prev.filter(x => x._id !== id));
    socket.on('template:update', onUpd);
    socket.on('template:delete', onDel);
    return () => { socket.off('template:update', onUpd); socket.off('template:delete', onDel); };
  }, []);

  async function submit(id) {
    setBusyId(id);
    try { await Templates.submit(id); await load(); showToast('success', 'Template submitted to Meta.'); }
    catch (e) { showToast('error', 'Submit failed: ' + extractError(e)); }
    finally { setBusyId(null); }
  }
  async function refresh(id) {
    setBusyId(id);
    try { await Templates.refresh(id); await load(); }
    catch (e) { showToast('error', 'Refresh failed: ' + extractError(e)); }
    finally { setBusyId(null); }
  }
  async function remove(id) {
    if (!confirm('Delete this template?')) return;
    setBusyId(id);
    try { await Templates.delete(id); await load(); showToast('success', 'Template deleted.'); }
    catch (e) { showToast('error', 'Delete failed: ' + extractError(e)); }
    finally { setBusyId(null); }
  }

  return (
    <div className="fixed inset-0 bg-black/40 z-40 flex justify-end" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose?.(); }}>
      <div className="w-full max-w-lg bg-white h-full flex flex-col shadow-2xl relative" onMouseDown={(e) => e.stopPropagation()}>
        <div className="px-4 py-3 bg-wati-panel text-wati-text flex items-center justify-between border-b border-wati-border">
          <div className="font-semibold text-lg">Templates</div>
          <div className="flex items-center gap-1 text-wati-muted">
            <button onClick={sync} disabled={loading} title="Sync from Meta" className="p-2 rounded-full hover:bg-black/5"><RefreshCw size={20} className={loading ? 'animate-spin' : ''} /></button>
            <button onClick={() => setEditing(true)} title="New template" className="p-2 rounded-full hover:bg-black/5"><Plus size={20} /></button>
            <button onClick={onClose} className="p-2 rounded-full hover:bg-black/5"><X size={20} /></button>
          </div>
        </div>

        <div className="flex-1 overflow-y-auto thin-scroll p-3 space-y-3">
          {list.length === 0 && loading ? (
            <><TemplateSkeleton /><TemplateSkeleton /><TemplateSkeleton /></>
          ) : list.length === 0 ? (
            <div className="text-center text-wati-muted text-sm py-8">No templates yet. Click <Plus size={14} className="inline" /> to create one.</div>
          ) : (
            list.map(t => {
              const badge = STATUS_BADGE[t.status] || STATUS_BADGE.DRAFT;
              const isExpanded = expandedId === t._id;
              const isPicked = pickedId === t._id;
              const canPick = t.status === 'APPROVED';
              return (
                <div key={t._id} className={clsx('border rounded-lg bg-white overflow-hidden transition-colors', isPicked ? 'border-wati-primary ring-1 ring-wati-primary/40' : 'border-gray-200')}>
                  <div className="flex items-center gap-2 px-3 py-2">
                    <input type="checkbox" checked={isPicked} disabled={!canPick} onChange={() => setPickedId(isPicked ? null : t._id)}
                      className="w-4 h-4 accent-wati-primary disabled:opacity-40 cursor-pointer" title={canPick ? 'Select to send' : 'Only APPROVED templates can be sent'} />
                    <div className="flex-1 min-w-0">
                      <div className="font-medium text-sm truncate">{t.name}</div>
                      <div className="text-[11px] text-wati-muted">{t.category} · {t.language}</div>
                    </div>
                    <span className={clsx('text-[11px] border rounded px-1.5 py-0.5 inline-flex items-center gap-1 shrink-0', badge.cls)}>{badge.icon} {t.status}</span>
                    <button onClick={() => setExpandedId(isExpanded ? null : t._id)} className="p-1.5 rounded hover:bg-gray-100 text-wati-muted shrink-0">
                      {isExpanded ? <EyeOff size={16} /> : <Eye size={16} />}
                    </button>
                  </div>
                  {isExpanded && (
                    <div className="px-3 pb-3 border-t bg-gray-50/50">
                      <TemplatePreview t={t} />
                      {t.rejectedReason && <div className="mt-2 text-xs text-red-600">Rejected: {t.rejectedReason}</div>}
                      <div className="mt-3 flex items-center gap-2">
                        {t.status === 'DRAFT' && <button onClick={() => submit(t._id)} disabled={busyId === t._id} className="px-3 py-1.5 text-xs bg-wati-primary text-white rounded disabled:opacity-60">{busyId === t._id ? 'Submitting…' : 'Submit to Meta'}</button>}
                        {t.status !== 'DRAFT' && t.status !== 'APPROVED' && (
                          <button onClick={() => refresh(t._id)} disabled={busyId === t._id} className="px-3 py-1.5 text-xs bg-gray-200 text-wati-text rounded flex items-center gap-1 disabled:opacity-60">
                            <RefreshCw size={14} className={busyId === t._id ? 'animate-spin' : ''} /> Refresh
                          </button>
                        )}
                        <button onClick={() => remove(t._id)} disabled={busyId === t._id} className="px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 rounded flex items-center gap-1 ml-auto disabled:opacity-60">
                          <Trash2 size={14} /> Delete
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              );
            })
          )}
        </div>

        {picked && (
          <div className="border-t bg-white p-3 flex items-center gap-3 shadow-[0_-4px_10px_rgba(0,0,0,0.04)]">
            <div className="flex-1 min-w-0">
              <div className="text-[11px] text-wati-muted">Selected template</div>
              <div className="text-sm font-medium truncate">{picked.name}</div>
            </div>
            <button onClick={() => { onPick(picked); setPickedId(null); }} className="px-4 py-2 text-sm bg-wati-primary text-white rounded flex items-center gap-2 hover:brightness-110">
              <Send size={16} /> Send
            </button>
          </div>
        )}

        {toast && (
          <div className={clsx('absolute bottom-4 left-4 right-4 z-30 px-3 py-2 rounded shadow-lg text-sm flex items-start gap-2', toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-green-600 text-white')}>
            <span className="flex-1 whitespace-pre-line">{toast.msg}</span>
            <button onClick={() => setToast(null)} className="opacity-80 hover:opacity-100"><X size={16} /></button>
          </div>
        )}

        {editing && <TemplateEditor onClose={() => setEditing(false)} onCreated={async () => { setEditing(false); await load(); }} />}
      </div>
    </div>
  );
}
