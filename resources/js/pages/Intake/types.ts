import type { Actor } from '../Access/Account';

export type { Actor };
export type CustomerChoice = { id: number; name: string };
export type RequestSummary = {
    id: string;
    customerId: number;
    customerName: string;
    material: string;
    color: string;
    quantity: string;
    status: 'SUBMITTED';
    submittedAt: string;
    detailUrl: string;
};
export type SubmittedFile = {
    id: string;
    name: string;
    format: 'STL' | '3MF';
    sizeBytes: number;
    available: boolean;
    downloadUrl: string;
};
export type UploadLimits = {
    maxFiles: number;
    maxFileBytes: number;
    maxTotalBytes: number;
};

export function fileSize(bytes: number): string {
    return `${(bytes / 1_048_576).toFixed(2)} MiB (${bytes.toLocaleString('en-US')} bytes)`;
}
