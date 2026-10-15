import * as DialogPrimitive from '@radix-ui/react-dialog';
import * as React from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * Confirmation dialog for destructive actions (shadcn AlertDialog API), built on the Radix dialog already in use:
 * role="alertdialog", no close button and no dismissal by clicking outside, so the admin answers it explicitly.
 * AlertDialogAction does not close the dialog by itself: the caller closes it once the action went through (or keeps it
 * open, e.g. while a required field is empty).
 */
function AlertDialog(props: React.ComponentProps<typeof DialogPrimitive.Root>) {
    return <DialogPrimitive.Root data-slot="alert-dialog" {...props} />;
}

function AlertDialogContent({ className, children, ...props }: React.ComponentProps<typeof DialogPrimitive.Content>) {
    return (
        <DialogPrimitive.Portal>
            <DialogPrimitive.Overlay
                data-slot="alert-dialog-overlay"
                className="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0"
            />
            <DialogPrimitive.Content
                data-slot="alert-dialog-content"
                role="alertdialog"
                onPointerDownOutside={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                className={cn(
                    'fixed top-[50%] left-[50%] z-50 grid w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 sm:max-w-lg',
                    className,
                )}
                {...props}
            >
                {children}
            </DialogPrimitive.Content>
        </DialogPrimitive.Portal>
    );
}

function AlertDialogHeader({ className, ...props }: React.ComponentProps<'div'>) {
    return <div data-slot="alert-dialog-header" className={cn('flex flex-col gap-2 text-center sm:text-left', className)} {...props} />;
}

function AlertDialogFooter({ className, ...props }: React.ComponentProps<'div'>) {
    return <div data-slot="alert-dialog-footer" className={cn('flex flex-col-reverse gap-2 sm:flex-row sm:justify-end', className)} {...props} />;
}

function AlertDialogTitle({ className, ...props }: React.ComponentProps<typeof DialogPrimitive.Title>) {
    return <DialogPrimitive.Title data-slot="alert-dialog-title" className={cn('text-lg font-semibold', className)} {...props} />;
}

function AlertDialogDescription({ className, ...props }: React.ComponentProps<typeof DialogPrimitive.Description>) {
    return <DialogPrimitive.Description data-slot="alert-dialog-description" className={cn('text-sm text-muted-foreground', className)} {...props} />;
}

function AlertDialogAction(props: React.ComponentProps<typeof Button>) {
    return <Button type="button" {...props} />;
}

function AlertDialogCancel(props: React.ComponentProps<typeof Button>) {
    return (
        <DialogPrimitive.Close asChild>
            <Button type="button" variant="outline" {...props} />
        </DialogPrimitive.Close>
    );
}

export { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle };
