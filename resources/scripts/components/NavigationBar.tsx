import * as React from 'react';
import { useState, useEffect } from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faBars, faCogs, faLayerGroup, faSignOutAlt, faCloud } from '@fortawesome/free-solid-svg-icons';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import tw from 'twin.macro';
import styled, { keyframes } from 'styled-components/macro';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import Avatar from '@/components/Avatar';

/**
 * Cloud Server — Enhanced Top Navigation Bar
 *
 * Design goals (different from upstream Pterodactyl/NookTheme):
 *  - Layered gradient background with subtle glassmorphism blur.
 *  - A pill-shaped brand chip with cloud icon + name (instead of plain text).
 *  - Right-side action buttons grouped into a floating glass capsule.
 *  - Animated bottom border that lights up on hover/active (cyan→purple).
 *  - Smooth fade-in entrance animation.
 *  - Better mobile responsiveness (icons shrink, gap tightens).
 */

const navFadeIn = keyframes`
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
`;

const NavShell = styled.div`
    position: relative;
    z-index: 50;
    background:
        radial-gradient(circle at 20% 50%, rgba(79, 70, 229, 0.18) 0%, transparent 50%),
        radial-gradient(circle at 80% 50%, rgba(6, 182, 212, 0.12) 0%, transparent 50%),
        linear-gradient(180deg, #1f2937 0%, #111827 100%);
    backdrop-filter: blur(8px);
    box-shadow: 0 4px 20px -8px rgba(0, 0, 0, 0.5),
                inset 0 -1px 0 rgba(255, 255, 255, 0.04);
    animation: ${navFadeIn} 0.35s ease-out;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
`;

const BrandChip = styled(Link)`
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 6px 14px;
    border-radius: 10px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.18) 0%, rgba(6, 182, 212, 0.12) 100%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #f3f4f6;
    font-family: 'IBM Plex Sans', system-ui, sans-serif;
    font-size: 18px;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-decoration: none;
    transition: all 0.2s ease;

    &:hover {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.32) 0%, rgba(6, 182, 212, 0.24) 100%);
        border-color: rgba(99, 102, 241, 0.4);
        transform: translateY(-1px);
        box-shadow: 0 6px 18px -6px rgba(99, 102, 241, 0.4);
    }

    .brand-icon {
        color: #818cf8;
        font-size: 16px;
        filter: drop-shadow(0 0 6px rgba(99, 102, 241, 0.5));
    }
`;

const RightCapsule = styled.div`
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 6px;
    border-radius: 12px;
    background: rgba(17, 24, 39, 0.55);
    border: 1px solid rgba(255, 255, 255, 0.06);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);

    & > a,
    & > button,
    & > .navigation-link {
        ${tw`flex items-center justify-center no-underline text-neutral-300 cursor-pointer transition-all duration-150`};
        width: 38px;
        height: 38px;
        border-radius: 8px;
        font-size: 15px;

        &:active,
        &:hover {
            background: rgba(99, 102, 241, 0.15);
            color: #c7d2fe;
        }

        &.active {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.28) 0%, rgba(6, 182, 212, 0.18) 100%);
            color: #fff;
            box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.3),
                        inset 0 -2px 0 #818cf8;
        }
    }

    @media (max-width: 640px) {
        gap: 2px;
        padding: 4px;

        & > a,
        & > button,
        & > .navigation-link {
            width: 34px;
            height: 34px;
            font-size: 13px;
        }
    }
`;

const SidebarToggle = styled.button`
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    margin-right: 8px;
    border-radius: 8px;
    background: rgba(17, 24, 39, 0.55);
    border: 1px solid rgba(255, 255, 255, 0.06);
    color: #d1d5db;
    cursor: pointer;
    transition: all 0.15s ease;

    &:hover {
        background: rgba(99, 102, 241, 0.15);
        color: #c7d2fe;
        border-color: rgba(99, 102, 241, 0.3);
    }
`;

const onTriggerNavButton = () => {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('active-nav');
    }
};

export default () => {
    const name = useStoreState((state: ApplicationStore) => state.settings.data!.name);
    const rootAdmin = useStoreState((state: ApplicationStore) => state.user.data!.rootAdmin);
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const location = useLocation();
    const [showSidebar, setShowSidebar] = useState(false);

    useEffect(() => {
        if (location.pathname.startsWith('/server') || location.pathname.startsWith('/account')) {
            setShowSidebar(true);
            return;
        }
        setShowSidebar(false);
    }, [location.pathname]);

    const onTriggerLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    return (
        <NavShell className={'topbar'}>
            <SpinnerOverlay visible={isLoggingOut} />
            <div className={'mx-auto w-full flex items-center h-[3.75rem] max-w-[1280px] px-4'}>
                {showSidebar && (
                    <SidebarToggle onClick={onTriggerNavButton} aria-label={'Toggle sidebar'}>
                        <FontAwesomeIcon icon={faBars} />
                    </SidebarToggle>
                )}

                <div id={'logo'} className={'flex-1'}>
                    <BrandChip to={'/'}>
                        <FontAwesomeIcon icon={faCloud} className={'brand-icon'} />
                        <span>{name}</span>
                    </BrandChip>
                </div>

                <RightCapsule>
                    <SearchContainer />
                    <Tooltip placement={'bottom'} content={'Dashboard'}>
                        <NavLink to={'/'} exact>
                            <FontAwesomeIcon icon={faLayerGroup} />
                        </NavLink>
                    </Tooltip>
                    {rootAdmin && (
                        <Tooltip placement={'bottom'} content={'Admin Control'}>
                            <a href={'/admin'} rel={'noreferrer'}>
                                <FontAwesomeIcon icon={faCogs} />
                            </a>
                        </Tooltip>
                    )}
                    <Tooltip placement={'bottom'} content={'Account Settings'}>
                        <NavLink to={'/account'}>
                            <span className={'flex items-center w-5 h-5'}>
                                <Avatar.User />
                            </span>
                        </NavLink>
                    </Tooltip>
                    <Tooltip placement={'bottom'} content={'Sign Out'}>
                        <button onClick={onTriggerLogout} aria-label={'Sign out'}>
                            <FontAwesomeIcon icon={faSignOutAlt} />
                        </button>
                    </Tooltip>
                </RightCapsule>
            </div>
        </NavShell>
    );
};
