import React from 'react';
import { X, CheckCircle, AlertCircle, Info, AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useToast, type Toast } from '@/contexts/ToastContext';

const iconMap = {
    success: CheckCircle,
    error: AlertCircle,
    info: Info,
    warning: AlertTriangle,
};

const colorMap = {
    success: 'bg-green-50 border-green-200 text-green-800',
    error: 'bg-red-50 border-red-200 text-red-800',
    info: 'bg-blue-50 border-blue-200 text-blue-800',
    warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
};

const iconColorMap = {
    success: 'text-green-400',
    error: 'text-red-400',
    info: 'text-blue-400',
    warning: 'text-yellow-400',
};

interface ToastItemProps {
    toast: Toast;
}

function ToastItem({ toast }: ToastItemProps) {
    const { removeToast } = useToast();
    const Icon = iconMap[toast.type];

    return (
        <div className={`max-w-sm w-full border rounded-lg shadow-lg pointer-events-auto ${colorMap[toast.type]}`}>
            <div className="p-4">
                <div className="flex items-start">
                    <div className="flex-shrink-0">
                        <Icon className={`h-5 w-5 ${iconColorMap[toast.type]}`} />
                    </div>
                    <div className="ml-3 w-0 flex-1 pt-0.5">
                        <p className="text-sm font-medium">
                            {toast.title}
                        </p>
                        {toast.description && (
                            <p className="mt-1 text-sm opacity-90">
                                {toast.description}
                            </p>
                        )}
                    </div>
                    <div className="ml-4 flex-shrink-0 flex">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 w-8 p-0 hover:bg-black/10"
                            onClick={() => removeToast(toast.id)}
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}

export function ToastContainer() {
    const { toasts } = useToast();

    if (toasts.length === 0) return null;

    return (
        <div
            aria-live="assertive"
            className="fixed top-0 right-0 z-50 flex flex-col items-end justify-start w-full max-w-sm space-y-4 p-6 pointer-events-none"
        >
            {toasts.map((toast) => (
                <ToastItem key={toast.id} toast={toast} />
            ))}
        </div>
    );
}
