export type UploadStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface UploadSummary {
    id: number;
    original_filename: string;
    status: UploadStatus;
    status_label: string;
    error: string | null;
    job_code: string | null;
    job_title: string | null;
    rate_with_ai: boolean;
    uploaded_by: string | null;
    total: number;
    created_count: number;
    skipped_count: number;
    failed_count: number;
    has_issues: boolean;
    created_at: string | null;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedData<T> {
    data: T[];
    links: PaginationLink[];
    total: number;
}

export const uploadStatusVariant: Record<
    UploadStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'outline',
    processing: 'secondary',
    completed: 'default',
    failed: 'destructive',
};
