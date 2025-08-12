import React from 'react';

interface LoadingOverlayProps {
    isLoading: boolean;
    children: React.ReactNode;
    className?: string;
}

/**
 * LoadingOverlay Component
 * 
 * A reusable loading overlay component that displays a spinner overlay
 * while content is loading and dims the background content.
 * 
 * @param isLoading - Whether to show the loading overlay
 * @param children - The content to wrap with the loading overlay
 * @param className - Additional CSS classes for the content wrapper
 */
export default function LoadingOverlay({ isLoading, children, className = '' }: LoadingOverlayProps) {
    return (
        <>
            {/* Loading Overlay */}
            {isLoading && (
                <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
                    <div className="rounded-lg p-4 sm:p-6 flex items-center space-x-3 max-w-xs">
                        <div className="animate-spin rounded-full h-9 w-9 sm:h-6 sm:w-6 border-b-3 border-white flex-shrink-0"></div>
                    </div>
                </div>
            )}
            
            {/* Content with loading state styling */}
            <div className={`transition-opacity duration-300 ${isLoading ? 'opacity-50' : 'opacity-100'} select-none ${className}`}>
                {children}
            </div>
        </>
    );
}
