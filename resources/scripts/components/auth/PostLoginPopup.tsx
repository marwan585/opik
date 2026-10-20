import * as React from 'react';
import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faTimes } from '@fortawesome/free-solid-svg-icons';
import styled, { keyframes } from 'styled-components/macro';

/**
 * Cloud Server — Post-Login Popup
 *
 * Shows a banner popup with image + text + close button when a user
 * lands on the dashboard after logging in. The popup config is injected
 * via `window.CloudServerPopup` by the AssetComposer (read from the
 * settings table by the panel owner via the Popup Settings admin
 * menu).
 *
 * Behaviour:
 *  - Shows ONCE per session, when the user first reaches the dashboard
 *    (/) after login.
 *  - Closing the popup (X button or Esc) dismisses it for the rest
 *    of the session (until the next full page reload / re-login).
 *  - If `enabled` is false, or image_url + text are both empty, the
 *    popup is not rendered.
 */

interface CloudServerPopupConfig {
    enabled: boolean;
    image_url: string;
    text: string;
    title?: string;
}

const getPopupConfig = (): CloudServerPopupConfig | null => {
    if (typeof window === 'undefined') return null;
    const cfg = (window as any).CloudServerPopup;
    if (!cfg || typeof cfg !== 'object') return null;
    return cfg as CloudServerPopupConfig;
};

const fadeIn = keyframes`
    from { opacity: 0; }
    to   { opacity: 1; }
`;

const popIn = keyframes`
    0%   { opacity: 0; transform: translateY(20px) scale(0.96); }
    60%  { opacity: 1; transform: translateY(-4px) scale(1.02); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
`;

const Overlay = styled.div`
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.78);
    backdrop-filter: blur(6px);
    animation: ${fadeIn} 0.2s ease-out;
`;

const Card = styled.div`
    position: relative;
    width: 90%;
    max-width: 560px;
    max-height: 85vh;
    overflow: hidden;
    border-radius: 18px;
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(129, 140, 248, 0.25);
    box-shadow:
        0 24px 60px -12px rgba(0, 0, 0, 0.65),
        0 0 0 1px rgba(255, 255, 255, 0.04),
        inset 0 1px 0 rgba(255, 255, 255, 0.05);
    animation: ${popIn} 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
`;

const CloseButton = styled.button`
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 10;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #e5e7eb;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    transition: all 0.15s ease;
    backdrop-filter: blur(4px);

    &:hover {
        background: rgba(239, 68, 68, 0.85);
        border-color: rgba(239, 68, 68, 1);
        color: #fff;
        transform: scale(1.08);
    }
`;

const ImageArea = styled.div`
    width: 100%;
    max-height: 280px;
    overflow: hidden;
    background: #0f172a;

    img {
        display: block;
        width: 100%;
        height: auto;
        max-height: 280px;
        object-fit: cover;
    }
`;

const ContentArea = styled.div`
    padding: 24px 28px 28px;
`;

const PopupTitle = styled.h3`
    margin: 0 0 12px;
    font-size: 20px;
    font-weight: 700;
    color: #f3f4f6;
    letter-spacing: 0.01em;
`;

const PopupText = styled.div`
    color: #cbd5e1;
    font-size: 15px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 280px;
    overflow-y: auto;

    &::-webkit-scrollbar { width: 6px; }
    &::-webkit-scrollbar-track { background: transparent; }
    &::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 3px; }
`;

/**
 * Props:
 *  - `trigger`: a string that changes whenever we want the popup to
 *    re-show. The parent (dashboard) passes the current path; when
 *    the path matches the dashboard index AND the popup hasn't been
 *    shown yet for this session, the popup appears.
 */
interface Props {
    trigger?: string;
}

// Module-level state — dismiss persists for the lifetime of the JS
// bundle (i.e. until the page is reloaded or the user logs out).
let hasShownThisSession = false;

export default ({ trigger }: Props) => {
    const location = useLocation();
    const [visible, setVisible] = useState(false);
    const [config, setConfig] = useState<CloudServerPopupConfig | null>(null);

    useEffect(() => {
        const cfg = getPopupConfig();

        // Debug log in development mode.
        if (process.env.NODE_ENV !== 'production') {
            // eslint-disable-next-line no-console
            console.log('[CloudServerPopup] trigger=', trigger, 'path=', location.pathname, 'config=', cfg);
        }

        if (!cfg || !cfg.enabled) return;

        // Don't show if both image_url and text are empty.
        if (!cfg.image_url && !cfg.text) return;

        // Don't show more than once per session.
        if (hasShownThisSession) return;

        // Show on the dashboard index route (/) — i.e. right after login.
        // Also accept any trigger change as a chance to show, so the parent
        // can force the popup by passing a different trigger string.
        const isDashboard = location.pathname === '/' || location.pathname === '';
        if (!isDashboard && !trigger) return;

        setConfig(cfg);
        setVisible(true);
        hasShownThisSession = true;
    }, [trigger, location.pathname]);

    const handleClose = () => {
        setVisible(false);
    };

    // Esc key to close
    useEffect(() => {
        if (!visible) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') handleClose();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [visible]);

    if (!visible || !config) return null;

    return (
        <Overlay role={'dialog'} aria-modal={'true'} aria-labelledby={'cloud-popup-title'}>
            <Card>
                <CloseButton onClick={handleClose} aria-label={'Close popup'}>
                    <FontAwesomeIcon icon={faTimes} />
                </CloseButton>

                {config.image_url && (
                    <ImageArea>
                        <img src={config.image_url} alt={config.title || 'Popup'} />
                    </ImageArea>
                )}

                <ContentArea>
                    {config.title && <PopupTitle id={'cloud-popup-title'}>{config.title}</PopupTitle>}
                    {config.text && <PopupText>{config.text}</PopupText>}
                </ContentArea>
            </Card>
        </Overlay>
    );
};
