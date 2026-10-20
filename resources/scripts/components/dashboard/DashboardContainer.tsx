import React, { useEffect, useMemo, useState } from 'react';
import { Server } from '@/api/server/getServer';
import getServers from '@/api/getServers';
import ServerRow from '@/components/dashboard/ServerRow';
import Spinner from '@/components/elements/Spinner';
import PageContentBlock from '@/components/elements/PageContentBlock';
import PostLoginPopup from '@/components/auth/PostLoginPopup';
import useFlash from '@/plugins/useFlash';
import { useStoreState } from 'easy-peasy';
import { usePersistedState } from '@/plugins/usePersistedState';
import Switch from '@/components/elements/Switch';
import tw from 'twin.macro';
import useSWR from 'swr';
import { PaginatedResult } from '@/api/http';
import Pagination from '@/components/elements/Pagination';
import { useLocation } from 'react-router-dom';
import styled, { keyframes } from 'styled-components/macro';

/**
 * Cloud Server — Modern Professional Dashboard
 *
 * Design language:
 *  - Dark premium palette (deep slate background, indigo accents).
 *  - Glassmorphism cards with subtle borders + soft shadows.
 *  - Gradient hero card with animated aurora blob.
 *  - Stat strip with icon + number + label per tile.
 *  - Section header with quick actions row.
 *  - Responsive grid for server cards.
 */

const fadeInUp = keyframes`
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
`;

const auroraShift = keyframes`
    0%   { transform: translate(0%, 0%) scale(1); opacity: 0.4; }
    50%  { transform: translate(20%, 30%) scale(1.2); opacity: 0.6; }
    100% { transform: translate(0%, 0%) scale(1); opacity: 0.4; }
`;

const HeroCard = styled.div`
    position: relative;
    overflow: hidden;
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    background:
        radial-gradient(circle at 15% 20%, rgba(99, 102, 241, 0.35) 0%, transparent 50%),
        radial-gradient(circle at 85% 80%, rgba(6, 182, 212, 0.25) 0%, transparent 50%),
        linear-gradient(135deg, #1e293b 0%, #312e81 50%, #1e1b4b 100%);
    border: 1px solid rgba(129, 140, 248, 0.18);
    box-shadow:
        0 20px 50px -15px rgba(79, 70, 229, 0.45),
        inset 0 1px 0 rgba(255, 255, 255, 0.06);
    animation: ${fadeInUp} 0.4s ease-out;
`;

const AuroraBlob = styled.div<{ $top?: string; $left?: string; $color?: string; $size?: string; $delay?: string }>`
    position: absolute;
    top: ${(p) => p.$top || '0'};
    left: ${(p) => p.$left || '0'};
    width: ${(p) => p.$size || '300px'};
    height: ${(p) => p.$size || '300px'};
    border-radius: 50%;
    background: radial-gradient(circle, ${(p) => p.$color || 'rgba(99,102,241,0.5)'} 0%, transparent 70%);
    filter: blur(40px);
    animation: ${auroraShift} 12s ease-in-out infinite;
    animation-delay: ${(p) => p.$delay || '0s'};
    pointer-events: none;
    z-index: 0;
`;

const HeroContent = styled.div`
    position: relative;
    z-index: 10;
    display: flex;
    flex-direction: column;
    gap: 20px;
    @media (min-width: 768px) {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
`;

const HeroBadge = styled.div`
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.18);
    border: 1px solid rgba(129, 140, 248, 0.3);
    color: #c7d2fe;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 14px;
    backdrop-filter: blur(8px);

    &::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #34d399;
        box-shadow: 0 0 8px #34d399;
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50%      { opacity: 0.4; }
    }
`;

const HeroTitle = styled.h1`
    margin: 0 0 8px;
    font-size: clamp(28px, 4vw, 40px);
    font-weight: 800;
    color: #ffffff;
    line-height: 1.1;
    letter-spacing: -0.02em;
`;

const HeroSubtitle = styled.p`
    margin: 0;
    font-size: 15px;
    color: rgba(226, 232, 240, 0.85);
    line-height: 1.6;
    max-width: 520px;
`;

const HeroStatPill = styled.div`
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 22px;
    border-radius: 14px;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    min-width: 200px;
`;

const StatNumber = styled.div`
    font-size: 32px;
    font-weight: 800;
    color: #ffffff;
    line-height: 1;
`;

const StatLabel = styled.div`
    font-size: 11px;
    color: rgba(148, 163, 184, 0.95);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-top: 4px;
`;

const StatsGrid = styled.div`
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
    margin-bottom: 28px;

    @media (min-width: 768px) {
        grid-template-columns: repeat(4, 1fr);
    }

    @media (max-width: 480px) {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
`;

const StatTile = styled.div<{ $accent: 'indigo' | 'cyan' | 'amber' | 'red' | 'emerald' }>`
    position: relative;
    padding: 18px 20px;
    border-radius: 14px;
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.05);
    box-shadow: 0 4px 16px -8px rgba(0, 0, 0, 0.4);
    overflow: hidden;
    animation: ${fadeInUp} 0.5s ease-out;

    &::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: ${(p) => {
            const colors = {
                indigo: 'linear-gradient(90deg, #6366f1, #818cf8)',
                cyan: 'linear-gradient(90deg, #06b6d4, #22d3ee)',
                amber: 'linear-gradient(90deg, #f59e0b, #fbbf24)',
                red: 'linear-gradient(90deg, #ef4444, #f87171)',
                emerald: 'linear-gradient(90deg, #10b981, #34d399)',
            };
            return colors[p.$accent];
        }};
    }
`;

const StatIcon = styled.div<{ $accent: 'indigo' | 'cyan' | 'amber' | 'red' | 'emerald' }>`
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    margin-bottom: 10px;
    font-size: 16px;
    color: ${(p) => {
        const colors = {
            indigo: '#818cf8',
            cyan: '#22d3ee',
            amber: '#fbbf24',
            red: '#f87171',
            emerald: '#34d399',
        };
        return colors[p.$accent];
    }};
    background: ${(p) => {
        const bg = {
            indigo: 'rgba(99, 102, 241, 0.15)',
            cyan: 'rgba(6, 182, 212, 0.15)',
            amber: 'rgba(245, 158, 11, 0.15)',
            red: 'rgba(239, 68, 68, 0.15)',
            emerald: 'rgba(16, 185, 129, 0.15)',
        };
        return bg[p.$accent];
    }};
`;

const StatValue = styled.div`
    font-size: 26px;
    font-weight: 700;
    color: #f1f5f9;
    line-height: 1;
`;

const StatName = styled.div`
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-top: 6px;
    font-weight: 500;
`;

const SectionHeader = styled.div`
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
`;

const SectionTitle = styled.h2`
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    font-size: 17px;
    font-weight: 600;
    color: #e2e8f0;
    letter-spacing: -0.01em;
`;

const SectionTitleDot = styled.span`
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #06b6d4);
    box-shadow: 0 0 10px rgba(99, 102, 241, 0.6);
`;

const ToggleWrap = styled.div`
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.06);
`;

const ToggleLabel = styled.span`
    font-size: 11px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 600;
`;

const EmptyState = styled.div`
    text-align: center;
    padding: 60px 24px;
    border-radius: 16px;
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.6) 0%, rgba(15, 23, 42, 0.6) 100%);
    border: 1px dashed rgba(255, 255, 255, 0.08);
`;

const EmptyIcon = styled.div`
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    border-radius: 16px;
    background: rgba(99, 102, 241, 0.1);
    color: #818cf8;
    font-size: 28px;
    margin-bottom: 16px;
`;

const EmptyTitle = styled.h3`
    margin: 0 0 8px;
    font-size: 18px;
    font-weight: 600;
    color: #e2e8f0;
`;

const EmptyDesc = styled.p`
    margin: 0;
    color: #64748b;
    font-size: 14px;
`;

export default () => {
    const { search } = useLocation();
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const username = useStoreState((state) => state.user.data!.username) || 'there';
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);

    const pteroUser = (typeof window !== 'undefined' ? (window as any).PterodactylUser : null) || {};
    const userId: number | undefined = pteroUser.id;
    const isOwner = !!(userId === 1 || (window as any).__CLOUD_SERVER_OWNER_ID === userId);
    const canShowAll = rootAdmin && isOwner;
    const [showOnlyAdmin, setShowOnlyAdmin] = usePersistedState(`${uuid}:show_all_servers`, false);

    useEffect(() => {
        if (!canShowAll && showOnlyAdmin) {
            setShowOnlyAdmin(false);
        }
    }, [canShowAll]);

    const { data: servers, error } = useSWR<PaginatedResult<Server>>(
        ['/api/client/servers', showOnlyAdmin && canShowAll, page],
        () => getServers({ page, type: showOnlyAdmin && canShowAll ? 'admin' : undefined })
    );

    useEffect(() => { setPage(1); }, [showOnlyAdmin]);
    useEffect(() => {
        if (!servers) return;
        if (servers.pagination.currentPage > 1 && !servers.items.length) setPage(1);
    }, [servers?.pagination.currentPage]);
    useEffect(() => {
        window.history.replaceState(null, document.title, `/${page <= 1 ? '' : `?page=${page}`}`);
    }, [page]);
    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'dashboard', error });
        if (!error) clearFlashes('dashboard');
    }, [error]);

    const suspendedCount = useMemo(
        () => (servers?.items || []).filter((s) => s.status === 'suspended').length,
        [servers]
    );
    const activeCount = useMemo(
        () => (servers?.items || []).filter((s) => !s.status || s.status === 'running').length,
        [servers]
    );
    const totalCount = servers?.pagination.total || servers?.items.length || 0;

    const hour = new Date().getHours();
    const greeting = hour < 11 ? 'Selamat pagi' : hour < 15 ? 'Selamat siang' : hour < 19 ? 'Selamat sore' : 'Selamat malam';

    return (
        <PageContentBlock className='content-dashboard' title={'Dashboard'} showFlashKey={'dashboard'}>
            <PostLoginPopup trigger={'dashboard-mount'} />

            <HeroCard>
                <AuroraBlob $top={'-20%'} $left={'60%'} $color={'rgba(99,102,241,0.5)'} $size={'320px'} />
                <AuroraBlob $top={'40%'} $left={'-10%'} $color={'rgba(6,182,212,0.4)'} $size={'280px'} $delay={'4s'} />
                <HeroContent>
                    <div style={{ flex: 1 }}>
                        <HeroBadge>
                            Cloud Server Control Panel
                        </HeroBadge>
                        <HeroTitle>
                            {greeting}, {username}!
                        </HeroTitle>
                        <HeroSubtitle>
                            {totalCount === 0
                                ? 'Anda belum punya server. Buat server baru untuk mulai mengelola layanan Cloud Server Anda.'
                                : `Anda memiliki ${totalCount} server aktif yang siap dikelola. ${suspendedCount > 0 ? `⚠️ ${suspendedCount} server sedang disuspend.` : 'Semua sistem berjalan normal.'}`}
                        </HeroSubtitle>
                    </div>
                    <HeroStatPill>
                        <div style={{ flex: 1 }}>
                            <StatNumber>{totalCount}</StatNumber>
                            <StatLabel>Total Server</StatLabel>
                        </div>
                    </HeroStatPill>
                </HeroContent>
            </HeroCard>

            <StatsGrid>
                <StatTile $accent={'indigo'}>
                    <StatIcon $accent={'indigo'}>
                        <span style={{ fontSize: 16 }}>☁</span>
                    </StatIcon>
                    <StatValue>{totalCount}</StatValue>
                    <StatName>Total Server</StatName>
                </StatTile>
                <StatTile $accent={'emerald'}>
                    <StatIcon $accent={'emerald'}>
                        <span style={{ fontSize: 16 }}>●</span>
                    </StatIcon>
                    <StatValue>{activeCount}</StatValue>
                    <StatName>Server Aktif</StatName>
                </StatTile>
                <StatTile $accent={'red'}>
                    <StatIcon $accent={'red'}>
                        <span style={{ fontSize: 16 }}>!</span>
                    </StatIcon>
                    <StatValue>{suspendedCount}</StatValue>
                    <StatName>Suspend</StatName>
                </StatTile>
                <StatTile $accent={'cyan'}>
                    <StatIcon $accent={'cyan'}>
                        <span style={{ fontSize: 16 }}>≈</span>
                    </StatIcon>
                    <StatValue>{servers?.pagination.totalPages || 1}</StatValue>
                    <StatName>Halaman</StatName>
                </StatTile>
            </StatsGrid>

            <SectionHeader>
                <SectionTitle>
                    <SectionTitleDot />
                    {showOnlyAdmin && canShowAll ? 'Server Lainnya' : 'Server Saya'}
                </SectionTitle>
                {canShowAll && (
                    <ToggleWrap>
                        <ToggleLabel>{showOnlyAdmin ? 'Server lain' : 'Server saya'}</ToggleLabel>
                        <Switch
                            name={'show_all_servers'}
                            defaultChecked={showOnlyAdmin}
                            onChange={() => setShowOnlyAdmin((s) => !s)}
                        />
                    </ToggleWrap>
                )}
            </SectionHeader>

            {!servers ? (
                <div style={{ padding: '60px 0', textAlign: 'center' }}>
                    <Spinner centered size={'large'} />
                </div>
            ) : (
                <Pagination data={servers} onPageSelect={setPage}>
                    {({ items }) =>
                        items.length > 0 ? (
                            items.map((server, index) => (
                                <ServerRow
                                    key={server.uuid}
                                    server={server}
                                    css={index > 0 ? tw`mt-3` : undefined}
                                />
                            ))
                        ) : (
                            <EmptyState>
                                <EmptyIcon>
                                    <span>☁</span>
                                </EmptyIcon>
                                <EmptyTitle>Belum ada server</EmptyTitle>
                                <EmptyDesc>
                                    {showOnlyAdmin
                                        ? 'Tidak ada server lain untuk ditampilkan.'
                                        : 'Belum ada server yang terhubung dengan akun Anda. Hubungi admin atau buat server baru.'}
                                </EmptyDesc>
                            </EmptyState>
                        )
                    }
                </Pagination>
            )}
        </PageContentBlock>
    );
};
