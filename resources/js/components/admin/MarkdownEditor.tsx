import RichText from '@/components/product/RichText';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { ImagePlus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { route } from 'ziggy-js';

interface MarkdownEditorProps {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    /** Same limit as the server (MarkdownRenderer::MAX_LENGTH) */
    maxLength?: number;
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

/**
 * Markdown textarea with Write / Preview tabs and a photo button. The preview comes from the server renderer, so it
 * shows exactly what customers will see. Tabs (not side by side) keep it usable on a phone.
 */
export function MarkdownEditor({ value, onChange, placeholder, maxLength = 10000 }: MarkdownEditorProps) {
    const { t } = useTranslation();
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [tab, setTab] = useState<'write' | 'preview'>('write');
    const [previewHtml, setPreviewHtml] = useState('');
    const [previewFailed, setPreviewFailed] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [uploadFailed, setUploadFailed] = useState(false);

    useEffect(() => {
        if (tab !== 'preview') {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const response = await fetch(route('admin.products.markdown-preview'), {
                    method: 'POST',
                    signal: controller.signal,
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    body: JSON.stringify({ markdown: value }),
                });
                if (!response.ok) {
                    throw new Error(String(response.status));
                }
                setPreviewHtml((await response.json()).html);
                setPreviewFailed(false);
            } catch {
                if (!controller.signal.aborted) {
                    setPreviewFailed(true);
                }
            }
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [tab, value]);

    const insertAtCursor = (text: string) => {
        const textarea = textareaRef.current;
        const start = textarea?.selectionStart ?? value.length;
        const end = textarea?.selectionEnd ?? value.length;
        onChange(value.slice(0, start) + text + value.slice(end));
    };

    const uploadPhoto = async (file: File) => {
        setUploading(true);
        setUploadFailed(false);
        try {
            const body = new FormData();
            body.append('image', file);
            const response = await fetch(route('admin.products.description-images'), {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body,
            });
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            const { url } = await response.json();
            // The file name is the starting alt text; the admin can edit it between the brackets
            insertAtCursor(`\n![${file.name.replace(/\.[^.]+$/, '')}](${url})\n`);
        } catch {
            setUploadFailed(true);
        } finally {
            setUploading(false);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        }
    };

    return (
        <Tabs value={tab} onValueChange={(next) => setTab(next as 'write' | 'preview')}>
            <div className="flex items-center justify-between gap-2">
                <TabsList>
                    <TabsTrigger value="write">{t('admin.products.markdown.write')}</TabsTrigger>
                    <TabsTrigger value="preview">{t('admin.products.markdown.preview')}</TabsTrigger>
                </TabsList>
                <Button type="button" variant="outline" size="sm" disabled={uploading} onClick={() => fileInputRef.current?.click()}>
                    <ImagePlus className="mr-1 h-4 w-4" />
                    {uploading ? t('admin.products.markdown.uploading') : t('admin.products.markdown.add_photo')}
                </Button>
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    className="hidden"
                    onChange={(event) => event.target.files?.[0] && uploadPhoto(event.target.files[0])}
                />
            </div>

            <TabsContent value="write" className="mt-2">
                <Textarea
                    ref={textareaRef}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={placeholder}
                    rows={8}
                    maxLength={maxLength}
                />
                <div className="mt-1 flex justify-between gap-2 text-xs text-gray-500">
                    <span>{t('admin.products.markdown.hint')}</span>
                    <span className="shrink-0">{t('admin.products.markdown.counter', { count: [...value].length, max: maxLength })}</span>
                </div>
            </TabsContent>

            <TabsContent value="preview" className="mt-2 min-h-[10rem] rounded-md border border-input p-3">
                {previewFailed ? (
                    <p className="text-sm text-red-500">{t('admin.products.markdown.preview_error')}</p>
                ) : previewHtml ? (
                    <RichText html={previewHtml} />
                ) : (
                    <p className="text-sm text-gray-500">{t('admin.products.markdown.preview_empty')}</p>
                )}
            </TabsContent>

            {uploadFailed && <p className="mt-1 text-sm text-red-500">{t('admin.products.markdown.upload_error')}</p>}
        </Tabs>
    );
}
