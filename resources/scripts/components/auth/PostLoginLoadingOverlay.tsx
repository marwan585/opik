import React from 'react';
import styled, { keyframes, css } from 'styled-components/macro';
import tw from 'twin.macro';

/**
 * Cloud Server — Post-Login Loading Overlay
 *
 * Shown for 2 seconds after successful login to give the user a smooth
 * transition animation before the dashboard renders. This is purely
 * cosmetic — the actual session is already authenticated server-side.
 */

const spin = keyframes`
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
`;

const pulse = keyframes`
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(0.95); }
`;

const float = keyframes`
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
`;

const shimmer = keyframes`
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
`;

const Overlay = styled.div`
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: radial-gradient(circle at 50% 30%, #1a2332 0%, #0a0e14 80%);
    color: #ecf0f1;
`;

const Spinner = styled.div`
    width: 76px;
    height: 76px;
    border: 4px solid rgba(52, 152, 219, 0.15);
    border-top-color: #3498db;
    border-right-color: #3498db;
    border-radius: 50%;
    animation: ${spin} 1s linear infinite, ${pulse} 1.6s ease-in-out infinite;
    box-shadow: 0 0 28px rgba(52, 152, 219, 0.45);
`;

const CloudIcon = styled.div`
    font-size: 42px;
    color: #3498db;
    animation: ${float} 1.8s ease-in-out infinite;
    margin-bottom: 18px;
    text-shadow: 0 0 20px rgba(52, 152, 219, 0.6);
`;

const Brand = styled.div`
    margin-top: 24px;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: #ecf0f1;
`;

const SubText = styled.div`
    margin-top: 8px;
    font-size: 13px;
    color: #95a5a6;
    letter-spacing: 0.02em;
`;

const ProgressBar = styled.div`
    margin-top: 22px;
    width: 220px;
    height: 4px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    overflow: hidden;

    &::after {
        content: '';
        display: block;
        height: 100%;
        width: 40%;
        border-radius: 999px;
        background: linear-gradient(90deg, #3498db 0%, #2ecc71 50%, #3498db 100%);
        background-size: 200% 100%;
        animation: ${shimmer} 1.2s linear infinite;
    }
`;

interface Props {
    /** Total visible duration in ms. Default 2000ms. */
    duration?: number;
    /** Optional brand text shown under the spinner. */
    brand?: string;
    /** Called when the loading timer elapses; the parent uses this
     *  to navigate to the post-login destination. */
    onComplete?: () => void;
}

/**
 * Renders a fixed full-screen loading overlay for `duration` ms, then
 * calls `onComplete` so the parent component can navigate to the
 * intended page.
 */
const PostLoginLoadingOverlay: React.FC<Props> = ({ duration = 2000, brand = 'Cloud Server', onComplete }) => {
    React.useEffect(() => {
        const t = setTimeout(() => {
            if (typeof onComplete === 'function') onComplete();
        }, duration);
        return () => clearTimeout(t);
    }, [duration, onComplete]);

    return (
        <Overlay role={'status'} aria-live={'polite'}>
            <CloudIcon>
                <i className={'fa fa-cloud'} style={{ fontStyle: 'normal' }}>
                    <svg width={'42'} height={'42'} viewBox={'0 0 24 24'} fill={'none'} stroke={'currentColor'} strokeWidth={'2'} strokeLinecap={'round'} strokeLinejoin={'round'}>
                        <path d={'M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z'} />
                    </svg>
                </i>
            </CloudIcon>
            <Spinner />
            <Brand>{brand}</Brand>
            <SubText>Securing your session, please wait…</SubText>
            <ProgressBar />
        </Overlay>
    );
};

export default PostLoginLoadingOverlay;
