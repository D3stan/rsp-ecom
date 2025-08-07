import { type ReactNode } from 'react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface Column<T> {
    key: keyof T | string;
    header: string;
    render?: (item: T) => ReactNode;
    className?: string;
}

interface PaginationInfo {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface DataTableProps<T> {
    data: T[];
    columns: Column<T>[];
    pagination?: PaginationInfo;
    onPageChange?: (page: number) => void;
    searchValue?: string;
    onSearchChange?: (value: string) => void;
    searchPlaceholder?: string;
    emptyMessage?: string;
    loading?: boolean;
}

export function DataTable<T extends Record<string, unknown>>({
    data,
    columns,
    pagination,
    onPageChange,
    searchValue,
    onSearchChange,
    searchPlaceholder = "Search...",
    emptyMessage = "No data available",
    loading = false
}: DataTableProps<T>) {
    const getValue = (item: T, key: string) => {
        return key.split('.').reduce((obj: unknown, k: string) => {
            if (typeof obj === 'object' && obj !== null) {
                return (obj as Record<string, unknown>)[k];
            }
            return undefined;
        }, item);
    };

    return (
        <Card className="p-0">
            {/* Search Bar */}
            {onSearchChange && (
                <div className="p-4 border-b">
                    <Input
                        placeholder={searchPlaceholder}
                        value={searchValue || ''}
                        onChange={(e) => onSearchChange(e.target.value)}
                        className="max-w-sm"
                    />
                </div>
            )}

            {/* Table */}
            <div className="overflow-x-auto">
                <table className="w-full">
                    <thead className="border-b bg-muted/50">
                        <tr>
                            {columns.map((column, index) => (
                                <th
                                    key={index}
                                    className={`text-left font-medium text-muted-foreground px-4 py-3 ${column.className || ''}`}
                                >
                                    {column.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {loading ? (
                            <tr>
                                <td colSpan={columns.length} className="px-4 py-8 text-center">
                                    <div className="flex items-center justify-center">
                                        <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
                                        <span className="ml-2">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        ) : data.length === 0 ? (
                            <tr>
                                <td colSpan={columns.length} className="px-4 py-8 text-center text-muted-foreground">
                                    {emptyMessage}
                                </td>
                            </tr>
                        ) : (
                            data.map((item, rowIndex) => (
                                <tr key={rowIndex} className="border-b hover:bg-muted/50">
                                    {columns.map((column, colIndex) => (
                                        <td key={colIndex} className={`px-4 py-3 ${column.className || ''}`}>
                                            {column.render
                                                ? column.render(item)
                                                : String(getValue(item, column.key as string) || '')
                                            }
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {pagination && pagination.last_page > 1 && (
                <div className="flex items-center justify-between px-4 py-3 border-t">
                    <div className="text-sm text-muted-foreground">
                        Showing {pagination.from} to {pagination.to} of {pagination.total} results
                    </div>
                    <div className="flex items-center space-x-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onPageChange?.(pagination.current_page - 1)}
                            disabled={pagination.current_page === 1}
                        >
                            <ChevronLeft className="h-4 w-4" />
                            Previous
                        </Button>
                        <span className="text-sm">
                            Page {pagination.current_page} of {pagination.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onPageChange?.(pagination.current_page + 1)}
                            disabled={pagination.current_page === pagination.last_page}
                        >
                            Next
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}
        </Card>
    );
}
