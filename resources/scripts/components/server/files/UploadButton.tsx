import axios, { AxiosProgressEvent } from 'axios';
import getFileUploadUrl from '@/api/server/files/getFileUploadUrl';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button/index';
import React, { useEffect, useRef, useState } from 'react';
import { ModalMask } from '@/components/elements/Modal';
import Fade from '@/components/elements/Fade';
import useEventListener from '@/plugins/useEventListener';
import { useFlashKey } from '@/plugins/useFlash';
import useFileManagerSwr from '@/plugins/useFileManagerSwr';
import { ServerContext } from '@/state/server';
import { WithClassname } from '@/components/types';
import Portal from '@/components/elements/Portal';
import { CloudUploadIcon } from '@heroicons/react/outline';
import { useSignal } from '@preact/signals-react';
import decompressFiles from '@/api/server/files/decompressFiles';
import deleteFiles from '@/api/server/files/deleteFiles';
import styled, { keyframes } from 'styled-components/macro';

function isFileOrDirectory(event: DragEvent): boolean {
    if (!event.dataTransfer?.types) {
        return false;
    }

    return event.dataTransfer.types.some((value) => value.toLowerCase() === 'files');
}

// List of file extensions that should be auto-extracted after upload.
// These are the same archive types that Pterodactyl's wings daemon
// supports for decompression.
const ARCHIVE_EXTENSIONS = ['.zip', '.tar', '.tar.gz', '.tgz', '.tar.bz2', '.tbz2', '.tar.xz', '.txz', '.gz', '.bz2', '.xz', '.rar', '.7z'];

function isArchiveFile(filename: string): boolean {
    const lower = filename.toLowerCase();
    // Check multi-part extensions first (tar.gz, tar.bz2, tar.xz)
    if (lower.endsWith('.tar.gz') || lower.endsWith('.tar.bz2') || lower.endsWith('.tar.xz')) {
        return true;
    }
    return ARCHIVE_EXTENSIONS.some((ext) => lower.endsWith(ext));
}

// Slide-in toast notification for auto-extract status.
const slideIn = keyframes`
    from { opacity: 0; transform: translateX(20px); }
    to   { opacity: 1; transform: translateX(0); }
`;

const ToastWrap = styled.div`
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-width: 360px;
    pointer-events: none;
`;

const Toast = styled.div<{ $type: 'info' | 'success' | 'error' }>`
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-radius: 10px;
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.98) 100%);
    border: 1px solid ${(p) => {
        if (p.$type === 'success') return 'rgba(16, 185, 129, 0.4)';
        if (p.$type === 'error') return 'rgba(239, 68, 68, 0.4)';
        return 'rgba(129, 140, 248, 0.4)';
    }};
    color: #f1f5f9;
    font-size: 13px;
    box-shadow: 0 8px 24px -8px rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(12px);
    animation: ${slideIn} 0.25s ease-out;
    pointer-events: auto;

    .toast-icon {
        flex-shrink: 0;
        width: 18px;
        height: 18px;
        color: ${(p) => {
            if (p.$type === 'success') return '#34d399';
            if (p.$type === 'error') return '#f87171';
            return '#818cf8';
        }};
    }

    .toast-spinner {
        flex-shrink: 0;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(129, 140, 248, 0.3);
        border-top-color: #818cf8;
        border-radius: 50%;
        animation: spin 0.7s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }
`;

interface ToastItem {
    id: number;
    type: 'info' | 'success' | 'error';
    message: string;
    isLoading?: boolean;
}

export default ({ className }: WithClassname) => {
    const fileUploadInput = useRef<HTMLInputElement>(null);

    const visible = useSignal(false);
    const timeouts = useSignal<NodeJS.Timeout[]>([]);

    const { mutate } = useFileManagerSwr();
    const { addError, clearAndAddHttpError } = useFlashKey('files');

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const directory = ServerContext.useStoreState((state) => state.files.directory);
    const { clearFileUploads, removeFileUpload, pushFileUpload, setUploadProgress } = ServerContext.useStoreActions(
        (actions) => actions.files
    );

    // Toast notifications for auto-extract status
    const [toasts, setToasts] = useState<ToastItem[]>([]);
    const toastIdRef = useRef(0);

    const showToast = (type: 'info' | 'success' | 'error', message: string, isLoading = false) => {
        const id = ++toastIdRef.current;
        setToasts((prev) => [...prev, { id, type, message, isLoading }]);
        if (!isLoading) {
            // Auto-dismiss after 4 seconds for success/error
            setTimeout(() => {
                setToasts((prev) => prev.filter((t) => t.id !== id));
            }, 4000);
        }
        return id;
    };

    const updateToast = (id: number, type: 'info' | 'success' | 'error', message: string, isLoading = false) => {
        setToasts((prev) => prev.map((t) => (t.id === id ? { ...t, type, message, isLoading } : t)));
        if (!isLoading) {
            setTimeout(() => {
                setToasts((prev) => prev.filter((t) => t.id !== id));
            }, 4000);
        }
    };

    const dismissToast = (id: number) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    };

    useEventListener(
        'dragenter',
        (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (isFileOrDirectory(e)) {
                visible.value = true;
            }
        },
        { capture: true }
    );

    useEventListener('dragexit', () => (visible.value = false), { capture: true });

    useEventListener('keydown', () => (visible.value = false));

    useEffect(() => {
        return () => timeouts.value.forEach(clearTimeout);
    }, []);

    const onUploadProgress = (data: AxiosProgressEvent, name: string) => {
        setUploadProgress({ name, loaded: data.loaded });
    };

    /**
     * Auto-extract a zip/archive file after upload completes.
     * Shows a toast notification with progress + result.
     * After successful extraction, the original archive is deleted
     * to keep the directory clean (extracted files replace it).
     */
    const autoExtractArchive = async (filename: string): Promise<void> => {
        const toastId = showToast('info', `📦 Mengekstrak "${filename}"...`, true);

        try {
            await decompressFiles(uuid, directory, filename);
            updateToast(toastId, 'success', `✓ "${filename}" berhasil diekstrak`);

            // Delete the original archive file (extracted files replace it)
            // — best-effort, ignore errors
            try {
                await deleteFiles(uuid, directory, [filename]);
            } catch (e) {
                // ignore — original archive will remain, no big deal
            }
        } catch (error: any) {
            const msg = error?.response?.data?.errors?.[0]?.detail || error?.message || 'Unknown error';
            updateToast(toastId, 'error', `✗ Gagal ekstrak "${filename}": ${msg}`);
        }
    };

    const onFileSubmission = (files: FileList) => {
        clearAndAddHttpError();
        const list = Array.from(files);
        if (list.some((file) => !file.type && (!file.size || file.size === 4096))) {
            return addError('Folder uploads are not supported.', 'Error');
        }

        const uploads = list.map((file) => {
            const controller = new AbortController();
            pushFileUpload({
                name: file.name,
                data: { abort: controller, loaded: 0, total: file.size },
            });

            const isArchive = isArchiveFile(file.name);

            return () =>
                getFileUploadUrl(uuid).then((url) =>
                    axios
                        .post(
                            url,
                            { files: file },
                            {
                                signal: controller.signal,
                                headers: { 'Content-Type': 'multipart/form-data' },
                                params: { directory },
                                onUploadProgress: (data) => onUploadProgress(data, file.name),
                            }
                        )
                        .then(() => {
                            timeouts.value.push(setTimeout(() => removeFileUpload(file.name), 500));

                            // Auto-extract archive files after successful upload
                            if (isArchive) {
                                // Small delay to let the file listing refresh first
                                setTimeout(() => {
                                    autoExtractArchive(file.name).then(() => {
                                        // Refresh file listing after extraction
                                        mutate();
                                    });
                                }, 600);
                            }
                        })
                );
        });

        Promise.all(uploads.map((fn) => fn()))
            .then(() => mutate())
            .catch((error) => {
                clearFileUploads();
                clearAndAddHttpError(error);
            });
    };

    return (
        <>
            {/* Toast notifications for auto-extract status */}
            {toasts.length > 0 && (
                <Portal>
                    <ToastWrap>
                        {toasts.map((t) => (
                            <Toast key={t.id} $type={t.type}>
                                {t.isLoading ? (
                                    <div className={'toast-spinner'} />
                                ) : (
                                    <div className={'toast-icon'}>
                                        {t.type === 'success' ? (
                                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        ) : t.type === 'error' ? (
                                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        ) : (
                                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        )}
                                    </div>
                                )}
                                <span>{t.message}</span>
                            </Toast>
                        ))}
                    </ToastWrap>
                </Portal>
            )}

            <Portal>
                <Fade appear in={visible.value} timeout={75} key={'upload_modal_mask'} unmountOnExit>
                    <ModalMask
                        onClick={() => (visible.value = false)}
                        onDragOver={(e: React.DragEvent) => e.preventDefault()}
                        onDrop={(e: React.DragEvent) => {
                            e.preventDefault();
                            e.stopPropagation();

                            visible.value = false;
                            if (!e.dataTransfer?.files.length) return;

                            onFileSubmission(e.dataTransfer.files);
                        }}
                    >
                        <div className={'w-full flex items-center justify-center pointer-events-none'}>
                            <div
                                className={
                                    'flex items-center space-x-4 bg-black w-full ring-4 ring-blue-200 ring-opacity-60 rounded p-6 mx-10 max-w-sm'
                                }
                            >
                                <CloudUploadIcon className={'w-10 h-10 flex-shrink-0'} />
                                <p className={'font-header flex-1 text-lg text-neutral-100 text-center'}>
                                    Drag and drop files to upload.
                                </p>
                            </div>
                        </div>
                    </ModalMask>
                </Fade>
            </Portal>
            <input
                type={'file'}
                ref={fileUploadInput}
                css={tw`hidden`}
                onChange={(e) => {
                    if (!e.currentTarget.files) return;

                    onFileSubmission(e.currentTarget.files);
                    if (fileUploadInput.current) {
                        fileUploadInput.current.files = null;
                    }
                }}
                multiple
            />
            <Button className={className} onClick={() => fileUploadInput.current && fileUploadInput.current.click()}>
                Upload
            </Button>
        </>
    );
};
