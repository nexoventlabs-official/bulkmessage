import React, { useState, useRef, useEffect } from 'react';
import clsx from 'clsx';
import {
  Send, Paperclip, Smile, X, Image, Video, FileText, Mic, Square, LayoutTemplate, Lock,
} from 'lucide-react';
import EmojiPicker from 'emoji-picker-react';
import { Uploads } from '../api/client';
import { windowState } from '../utils/time';

export default function MessageInput({
  contact,
  onSendText,
  onSendMedia,
  onOpenTemplates,
  replyTo,
  onCancelReply,
}) {
  const [text, setText] = useState('');
  const [showEmoji, setShowEmoji] = useState(false);
  const [showAttach, setShowAttach] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [recording, setRecording] = useState(false);
  const textRef = useRef(null);
  const mediaRecorderRef = useRef(null);
  const chunksRef = useRef([]);
  const emojiRef = useRef(null);
  const attachRef = useRef(null);

  const ws = windowState(contact?.lastCustomerMessageAt);
  const windowOpen = !ws.expired;

  useEffect(() => {
    if (textRef.current) textRef.current.focus();
  }, [contact?._id]);

  useEffect(() => {
    const handler = (e) => {
      if (emojiRef.current && !emojiRef.current.contains(e.target)) setShowEmoji(false);
      if (attachRef.current && !attachRef.current.contains(e.target)) setShowAttach(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  function handleSend() {
    const trimmed = text.trim();
    if (!trimmed) return;
    onSendText(trimmed, replyTo?.wamid || null);
    setText('');
    onCancelReply?.();
  }

  function onKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  }

  async function handleFile(type, accept) {
    setShowAttach(false);
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = accept;
    input.onchange = async () => {
      const file = input.files?.[0];
      if (!file) return;
      setUploading(true);
      try {
        const res = await Uploads.upload(file, contact?.waId);
        onSendMedia({
          type,
          url: res.url,
          caption: '',
          filename: file.name,
        });
      } catch (e) {
        alert('Upload failed: ' + (e.response?.data?.error || e.message));
      } finally {
        setUploading(false);
      }
    };
    input.click();
  }

  async function startRecording() {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const mr = new MediaRecorder(stream, { mimeType: 'audio/webm' });
      chunksRef.current = [];
      mr.ondataavailable = (e) => chunksRef.current.push(e.data);
      mr.onstop = async () => {
        stream.getTracks().forEach(t => t.stop());
        const blob = new Blob(chunksRef.current, { type: 'audio/ogg' });
        const file = new File([blob], `voice_${Date.now()}.ogg`, { type: 'audio/ogg' });
        setUploading(true);
        try {
          const res = await Uploads.upload(file, contact?.waId);
          onSendMedia({ type: 'audio', url: res.url });
        } catch (e) {
          alert('Voice upload failed: ' + (e.response?.data?.error || e.message));
        } finally {
          setUploading(false);
        }
      };
      mediaRecorderRef.current = mr;
      mr.start();
      setRecording(true);
    } catch (e) {
      alert('Microphone access denied');
    }
  }

  function stopRecording() {
    mediaRecorderRef.current?.stop();
    setRecording(false);
  }

  if (!contact) {
    return (
      <div className="px-4 py-3 bg-wati-panel border-t text-center text-sm text-wati-muted">
        Select a contact to start chatting
      </div>
    );
  }

  if (!windowOpen) {
    return (
      <div className="px-4 py-3 bg-wati-panel border-t">
        <div className="flex items-center justify-center gap-2 text-sm text-wati-muted mb-2">
          <Lock size={14} /> 24-hour window closed — use a template to re-engage
        </div>
        <button
          onClick={onOpenTemplates}
          className="w-full py-2.5 bg-wati-primary text-white rounded-lg text-sm font-medium flex items-center justify-center gap-2 hover:brightness-110"
        >
          <LayoutTemplate size={16} /> Send Template
        </button>
      </div>
    );
  }

  return (
    <div className="bg-wati-panel border-t px-3 py-2">
      {replyTo && (
        <div className="flex items-center gap-2 mb-2 bg-white rounded px-3 py-2 border-l-4 border-wati-primary">
          <div className="flex-1 min-w-0 text-xs text-wati-text truncate">
            Replying to: {replyTo.text || replyTo.caption || `[${replyTo.type}]`}
          </div>
          <button onClick={onCancelReply} className="text-wati-muted hover:text-gray-800"><X size={14} /></button>
        </div>
      )}

      <div className="flex items-end gap-2">
        <div className="relative" ref={emojiRef}>
          <button onClick={() => setShowEmoji(v => !v)} className="p-2 text-wati-muted hover:text-gray-700 rounded-full hover:bg-black/5">
            <Smile size={22} />
          </button>
          {showEmoji && (
            <div className="absolute bottom-12 left-0 z-30">
              <EmojiPicker
                onEmojiClick={(e) => { setText(prev => prev + e.emoji); textRef.current?.focus(); }}
                width={320}
                height={350}
                searchDisabled
                skinTonesDisabled
                previewConfig={{ showPreview: false }}
              />
            </div>
          )}
        </div>

        <div className="relative" ref={attachRef}>
          <button onClick={() => setShowAttach(v => !v)} className="p-2 text-wati-muted hover:text-gray-700 rounded-full hover:bg-black/5" disabled={uploading}>
            <Paperclip size={22} className={uploading ? 'animate-spin' : ''} />
          </button>
          {showAttach && (
            <div className="absolute bottom-12 left-0 z-30 bg-white rounded-lg shadow-lg border py-2 min-w-[160px]">
              <button onClick={() => handleFile('image', 'image/*')} className="w-full flex items-center gap-2 px-3 py-2 hover:bg-gray-100 text-sm"><Image size={16} className="text-blue-600" /> Image</button>
              <button onClick={() => handleFile('video', 'video/*')} className="w-full flex items-center gap-2 px-3 py-2 hover:bg-gray-100 text-sm"><Video size={16} className="text-purple-600" /> Video</button>
              <button onClick={() => handleFile('document', '.pdf,.doc,.docx,.xlsx,.pptx,.txt,.csv')} className="w-full flex items-center gap-2 px-3 py-2 hover:bg-gray-100 text-sm"><FileText size={16} className="text-red-600" /> Document</button>
            </div>
          )}
        </div>

        <button onClick={onOpenTemplates} className="p-2 text-wati-muted hover:text-gray-700 rounded-full hover:bg-black/5" title="Templates">
          <LayoutTemplate size={22} />
        </button>

        <div className="flex-1 min-w-0">
          <textarea
            ref={textRef}
            value={text}
            onChange={e => setText(e.target.value)}
            onKeyDown={onKeyDown}
            placeholder="Type a message"
            rows={1}
            className="w-full resize-none rounded-lg px-3 py-2.5 text-sm bg-white border border-gray-200 outline-none focus:border-wati-primary thin-scroll"
            style={{ maxHeight: 120 }}
          />
        </div>

        {text.trim() ? (
          <button onClick={handleSend} className="p-2.5 bg-wati-primary text-white rounded-full hover:brightness-110">
            <Send size={20} />
          </button>
        ) : recording ? (
          <button onClick={stopRecording} className="p-2.5 bg-red-500 text-white rounded-full animate-pulse">
            <Square size={20} />
          </button>
        ) : (
          <button onClick={startRecording} className="p-2.5 text-wati-muted hover:text-gray-700 rounded-full hover:bg-black/5">
            <Mic size={22} />
          </button>
        )}
      </div>
    </div>
  );
}
