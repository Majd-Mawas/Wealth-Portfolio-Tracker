import { Landmark, LogOut, Menu, X } from "lucide-react";
import { useState } from "react";
import { Link, Outlet, useNavigate } from "react-router-dom";
import { authApi } from "../lib/api";
import { useAuth } from "../features/auth/auth-context";
import { Button } from "./ui/button";

export function AppShell() {
  const { user, setUser } = useAuth();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const logout = async () => {
    try {
      await authApi.logout();
    } finally {
      setUser(null);
      navigate("/login");
    }
  };

  return (
    <div className="min-h-screen bg-[#f3f5f2]">
      <header className="border-b border-[#dce2dd] bg-[#132820] text-white">
        <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
          <Link
            to="/portfolios"
            className="flex items-center gap-2.5 font-semibold tracking-[-0.01em]"
          >
            <Landmark size={21} />
            <span>Ledgerly</span>
          </Link>
          <button
            className="rounded p-2 md:hidden"
            onClick={() => setOpen((value) => !value)}
            aria-label="Toggle navigation"
          >
            {open ? <X size={20} /> : <Menu size={20} />}
          </button>
          <div className="hidden items-center gap-5 md:flex">
            <div className="text-right">
              <p className="text-sm font-medium">{user?.name}</p>
              <p className="text-xs text-[#b9c8c1]">{user?.email}</p>
            </div>
            <Button
              variant="ghost"
              size="sm"
              className="text-[#dce7e2] hover:bg-white/10 hover:text-white"
              onClick={logout}
            >
              <LogOut size={15} />
              Sign out
            </Button>
          </div>
        </div>
        {open && (
          <div className="border-t border-white/10 px-4 py-4 md:hidden">
            <p className="text-sm font-medium">{user?.name}</p>
            <p className="mb-3 text-xs text-[#b9c8c1]">{user?.email}</p>
            <Button
              variant="ghost"
              size="sm"
              className="text-white hover:bg-white/10"
              onClick={logout}
            >
              <LogOut size={15} />
              Sign out
            </Button>
          </div>
        )}
      </header>
      <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <Outlet />
      </main>
    </div>
  );
}
