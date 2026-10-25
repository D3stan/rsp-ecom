import { createContext, ReactNode, useContext, useState } from 'react';

export interface Toast {
    id: string;
    type: 'success' | 'error' | 'info' | 'warning';
    title: string;
    description?: string;
    duration?: number;
    leaving?: boolean;
}

interface ToastContextType {
    toasts: Toast[];
    addToast: (toast: Omit<Toast, 'id'>) => void;
    removeToast: (id: string) => void;
}

const ToastContext = createContext<ToastContextType | undefined>(undefined);

export function useToast() {
    const context = useContext(ToastContext);
    if (!context) {
        throw new Error('useToast must be used within a ToastProvider');
    }
    return context;
}

interface ToastProviderProps {
    children: ReactNode;
}

export function ToastProvider({ children }: ToastProviderProps) {
    const [toasts, setToasts] = useState<Toast[]>([]);

    const addToast = (toast: Omit<Toast, 'id'>) => {
        const id = Math.random().toString(36).slice(2);
        const newToast = { ...toast, id };

        setToasts((prev) => [...prev, newToast]);

        const duration = toast.duration || 5000;
        setTimeout(() => {
            removeToast(id);
        }, duration);
    };

    const removeToast = (id: string) => {
        setToasts((prev) => prev.map((toast) => (toast.id === id ? { ...toast, leaving: true } : toast)));
        setTimeout(() => {
            setToasts((prev) => prev.filter((toast) => toast.id !== id));
        }, 180);
    };

    return <ToastContext.Provider value={{ toasts, addToast, removeToast }}>{children}</ToastContext.Provider>;
}
