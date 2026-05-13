import React from 'react';
import ReactDOM from 'react-dom/client';
import './index.css';
import App from './App.jsx';
import AdminApp from './admin/AdminApp.jsx';

function Root() {
  const path = window.location.pathname;
  if (path.startsWith('/admin')) {
    return <AdminApp />;
  }
  return <App />;
}

ReactDOM.createRoot(document.getElementById('root')).render(<Root />);
