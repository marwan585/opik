import React, { useEffect, useState } from 'react';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import { PowerAction } from '@/components/server/console/ServerConsoleContainer';
import { Dialog } from '@/components/elements/dialog';
import styled from 'styled-components/macro';

interface PowerButtonProps {
    className?: string;
}

const Btn = styled.button<{ $variant: 'start' | 'restart' | 'stop' | 'kill'; $disabled?: boolean }>`
    flex: 1;
    min-width: 90px;
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 10px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.18s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    letter-spacing: 0.01em;

    ${(p) => {
        if (p.$variant === 'start') {
            return `
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: #ffffff;
                border-color: rgba(16, 185, 129, 0.4);
                box-shadow: 0 4px 12px -4px rgba(16, 185, 129, 0.5), inset 0 1px 0 rgba(255,255,255,0.15);

                &:hover:not(:disabled) {
                    background: linear-gradient(135deg, #059669 0%, #047857 100%);
                    box-shadow: 0 6px 18px -4px rgba(16, 185, 129, 0.65), inset 0 1px 0 rgba(255,255,255,0.2);
                    transform: translateY(-1px);
                }
            `;
        }
        if (p.$variant === 'restart') {
            return `
                background: rgba(99, 102, 241, 0.15);
                color: #c7d2fe;
                border-color: rgba(129, 140, 248, 0.3);

                &:hover:not(:disabled) {
                    background: rgba(99, 102, 241, 0.25);
                    border-color: rgba(129, 140, 248, 0.5);
                    color: #ffffff;
                }
            `;
        }
        // stop or kill
        return `
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #ffffff;
            border-color: rgba(239, 68, 68, 0.4);
            box-shadow: 0 4px 12px -4px rgba(239, 68, 68, 0.5), inset 0 1px 0 rgba(255,255,255,0.15);

            &:hover:not(:disabled) {
                background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
                box-shadow: 0 6px 18px -4px rgba(239, 68, 68, 0.65), inset 0 1px 0 rgba(255,255,255,0.2);
                transform: translateY(-1px);
            }
        `;
    }}

    &:active:not(:disabled) {
        transform: translateY(0);
    }

    &:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        transform: none !important;
    }

    svg {
        width: 14px;
        height: 14px;
    }

    @media (max-width: 480px) {
        padding: 9px 10px;
        font-size: 12px;
        min-width: 80px;
    }
`;

const StartIcon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
        <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" />
    </svg>
);

const RestartIcon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
    </svg>
);

const StopIcon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
        <path fillRule="evenodd" d="M4.5 7.5a3 3 0 013-3h5a3 3 0 013 3v5a3 3 0 01-3 3h-5a3 3 0 01-3-3v-5z" clipRule="evenodd" />
    </svg>
);

export default ({ className }: PowerButtonProps) => {
    const [open, setOpen] = useState(false);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);

    const killable = status === 'stopping';
    const onButtonClick = (
        action: PowerAction | 'kill-confirmed',
        e: React.MouseEvent<HTMLButtonElement, MouseEvent>
    ): void => {
        e.preventDefault();
        if (action === 'kill') {
            return setOpen(true);
        }

        if (instance) {
            setOpen(false);
            instance.send('set state', action === 'kill-confirmed' ? 'kill' : action);
        }
    };

    useEffect(() => {
        if (status === 'offline') {
            setOpen(false);
        }
    }, [status]);

    return (
        <div className={className} style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
            <Dialog.Confirm
                open={open}
                hideCloseIcon
                onClose={() => setOpen(false)}
                title={'Forcibly Stop Process'}
                confirm={'Continue'}
                onConfirmed={onButtonClick.bind(this, 'kill-confirmed')}
            >
                Forcibly stopping a server can lead to data corruption.
            </Dialog.Confirm>
            <Can action={'control.start'}>
                <Btn
                    $variant="start"
                    disabled={status !== 'offline'}
                    onClick={onButtonClick.bind(this, 'start')}
                >
                    <StartIcon />
                    <span>Start</span>
                </Btn>
            </Can>
            <Can action={'control.restart'}>
                <Btn
                    $variant="restart"
                    disabled={!status}
                    onClick={onButtonClick.bind(this, 'restart')}
                >
                    <RestartIcon />
                    <span>Restart</span>
                </Btn>
            </Can>
            <Can action={'control.stop'}>
                <Btn
                    $variant={killable ? 'stop' : 'stop'}
                    disabled={status === 'offline'}
                    onClick={onButtonClick.bind(this, killable ? 'kill' : 'stop')}
                >
                    <StopIcon />
                    <span>{killable ? 'Kill' : 'Stop'}</span>
                </Btn>
            </Can>
        </div>
    );
};
