import { Outlet, Link } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
export function CheckoutLayout(){return <div className="min-h-screen bg-[#fffaf5]"><header className="border-b border-ink-100 bg-white"><div className="container-page flex h-16 items-center justify-between"><Link to="/" className="font-display text-xl font-semibold">EverydayLighter</Link><Link to="/products" className="flex items-center gap-2 text-sm text-ink-500"><ArrowLeft className="h-4 w-4"/>Keep browsing</Link></div></header><main><Outlet/></main></div>}
