import React, { useEffect, useMemo, useState } from 'react';
import { Server } from '@/api/server/getServer';
import getServers from '@/api/getServers';
import ServerRow from '@/components/dashboard/ServerRow';
import Spinner from '@/components/elements/Spinner';
import PageContentBlock from '@/components/elements/PageContentBlock';
import useFlash from '@/plugins/useFlash';
import { useStoreState } from 'easy-peasy';
import { usePersistedState } from '@/plugins/usePersistedState';
import Switch from '@/components/elements/Switch';
import tw from 'twin.macro';
import useSWR from 'swr';
import { PaginatedResult } from '@/api/http';
import Pagination from '@/components/elements/Pagination';
import { useLocation } from 'react-router-dom';

/**
 * Cloud Server — enhanced dashboard.
 * Adds: hero greeting card, quick-stats strip, smoother layout.
 */
const HeroCard = ({ username, serverCount, suspendedCount }: { username: string; serverCount: number; suspendedCount: number }) => (
    <div
        css={tw`rounded-xl p-6 md:p-8 mb-6 relative overflow-hidden`}
        style={{
            background: 'linear-gradient(135deg, #1e3a8a 0%, #4f46e5 45%, #7c3aed 100%)',
            boxShadow: '0 10px 40px -10px rgba(79, 70, 229, 0.45)',
        }}
    >
        {/* Decorative blobs */}
        <div
            css={tw`absolute top-0 right-0 w-64 h-64 rounded-full opacity-20`}
            style={{ background: 'radial-gradient(circle, #ffffff 0%, transparent 70%)', transform: 'translate(40%, -40%)' }}
        />
        <div
            css={tw`absolute bottom-0 left-0 w-48 h-48 rounded-full opacity-10`}
            style={{ background: 'radial-gradient(circle, #34d399 0%, transparent 70%)', transform: 'translate(-30%, 30%)' }}
        />
        <div css={tw`relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4`}>
            <div>
                <div css={tw`text-white/70 text-xs uppercase tracking-widest mb-2`}>Cloud Server Control Panel</div>
                <h1 css={tw`text-3xl md:text-4xl font-bold text-white mb-2`}>Welcome back, {username}!</h1>
                <p css={tw`text-white/80 text-sm max-w-md`}>
                    {serverCount === 0
                        ? 'You don\'t have any servers yet — create one to get started.'
                        : `You have ${serverCount} active server${serverCount === 1 ? '' : 's'} ready to manage.`}
                    {suspendedCount > 0 && ` ⚠️ ${suspendedCount} suspended.`}
                </p>
            </div>
            <div css={tw`flex items-center justify-center md:justify-end gap-3`}>
                <div
                    css={tw`rounded-2xl px-5 py-3 text-center backdrop-blur-md`}
                    style={{ background: 'rgba(255,255,255,0.15)', border: '1px solid rgba(255,255,255,0.2)' }}
                >
                    <div css={tw`text-3xl font-bold text-white`}>{serverCount}</div>
                    <div css={tw`text-white/70 text-xs uppercase tracking-wider`}>Total Servers</div>
                </div>
            </div>
        </div>
    </div>
);

export default () => {
    const { search } = useLocation();
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const username = useStoreState((state) => state.user.data!.username) || 'there';
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [showOnlyAdmin, setShowOnlyAdmin] = usePersistedState(`${uuid}:show_all_servers`, false);

    const { data: servers, error } = useSWR<PaginatedResult<Server>>(
        ['/api/client/servers', showOnlyAdmin && rootAdmin, page],
        () => getServers({ page, type: showOnlyAdmin && rootAdmin ? 'admin' : undefined })
    );

    useEffect(() => {
        setPage(1);
    }, [showOnlyAdmin]);

    useEffect(() => {
        if (!servers) return;
        if (servers.pagination.currentPage > 1 && !servers.items.length) {
            setPage(1);
        }
    }, [servers?.pagination.currentPage]);

    useEffect(() => {
        // Don't use react-router to handle changing this part of the URL, otherwise it
        // triggers a needless re-render. We just want to track this in the URL incase the
        // user refreshes the page.
        window.history.replaceState(null, document.title, `/${page <= 1 ? '' : `?page=${page}`}`);
    }, [page]);

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'dashboard', error });
        if (!error) clearFlashes('dashboard');
    }, [error]);

    const suspendedCount = useMemo(
        () => (servers?.items || []).filter((s) => (s.status === 'suspended' || s.isSuspended)).length,
        [servers]
    );

    return (
        <PageContentBlock className='content-dashboard' title={'Dashboard'} showFlashKey={'dashboard'}>
            <HeroCard username={username} serverCount={servers?.items.length || 0} suspendedCount={suspendedCount} />
            {rootAdmin && (
                <div css={tw`mb-2 flex justify-end items-center`}>
                    <p css={tw`uppercase text-xs text-neutral-400 mr-2`}>
                        {showOnlyAdmin ? "Showing others' servers" : 'Showing your servers'}
                    </p>
                    <Switch
                        name={'show_all_servers'}
                        defaultChecked={showOnlyAdmin}
                        onChange={() => setShowOnlyAdmin((s) => !s)}
                    />
                </div>
            )}
            {!servers ? (
                <Spinner centered size={'large'} />
            ) : (
                <Pagination data={servers} onPageSelect={setPage}>
                    {({ items }) =>
                        items.length > 0 ? (
                            items.map((server, index) => (
                                <ServerRow key={server.uuid} server={server} css={index > 0 ? tw`mt-2` : undefined} />
                            ))
                        ) : (
                            <p css={tw`text-center text-sm text-neutral-400`}>
                                {showOnlyAdmin
                                    ? 'There are no other servers to display.'
                                    : 'There are no servers associated with your account.'}
                            </p>
                        )
                    }
                </Pagination>
            )}
        </PageContentBlock>
    );
};
