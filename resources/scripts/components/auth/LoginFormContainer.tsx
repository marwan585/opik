import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled, { keyframes } from 'styled-components/macro';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
};

/**
 * Cloud Server — Modern Professional Login Container
 *
 * Design language (v2 — polished):
 *  - Split-screen on desktop: left = brand showcase panel with
 *    animated grid + glow, right = clean login form.
 *  - Mobile: single card with brand header + form.
 *  - Subtle aurora gradient background (calmer, not distracting).
 *  - Modern typography: large brand wordmark, light subtitle.
 *  - Card has soft border, layered shadow, no harsh corners.
 *  - No spinning icons — keep it professional.
 */

const fadeIn = keyframes`
    from { opacity: 0; }
    to   { opacity: 1; }
`;

const slideUp = keyframes`
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
`;

const glowPulse = keyframes`
    0%, 100% { opacity: 0.6; }
    50%      { opacity: 1; }
`;

const PageShell = styled.div`
    position: relative;
    min-height: 100vh;
    display: flex;
    background:
        radial-gradient(ellipse at top left, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at bottom right, rgba(6, 182, 212, 0.10) 0%, transparent 50%),
        linear-gradient(135deg, #0b1020 0%, #0f172a 50%, #0b1020 100%);
    overflow: hidden;
    animation: ${fadeIn} 0.4s ease-out;
`;

// ===== Left showcase panel (desktop only) =====
const ShowcasePanel = styled.div`
    display: none;
    flex: 1.2;
    position: relative;
    padding: 56px 64px;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;

    @media (min-width: 1024px) {
        display: flex;
    }
`;

const GridOverlay = styled.div`
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(129, 140, 248, 0.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(129, 140, 248, 0.06) 1px, transparent 1px);
    background-size: 60px 60px;
    background-position: center;
    mask-image: radial-gradient(ellipse at center, black 30%, transparent 70%);
    -webkit-mask-image: radial-gradient(ellipse at center, black 30%, transparent 70%);
    pointer-events: none;
`;

const GlowOrb = styled.div`
    position: absolute;
    top: 20%;
    right: -10%;
    width: 480px;
    height: 480px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.35) 0%, transparent 70%);
    filter: blur(60px);
    animation: ${glowPulse} 6s ease-in-out infinite;
    pointer-events: none;
`;

const ShowcaseContent = styled.div`
    position: relative;
    z-index: 2;
    max-width: 480px;
    animation: ${slideUp} 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.1s both;
`;

const ShowcaseLogo = styled.div`
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 8px 14px;
    border-radius: 12px;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(129, 140, 248, 0.2);
    margin-bottom: 36px;
    backdrop-filter: blur(8px);

    svg {
        width: 22px;
        height: 22px;
        color: #818cf8;
    }

    span {
        font-size: 14px;
        font-weight: 600;
        color: #e2e8f0;
        letter-spacing: 0.01em;
    }
`;

const ShowcaseHeadline = styled.h1`
    margin: 0 0 18px;
    font-size: 44px;
    font-weight: 800;
    color: #ffffff;
    line-height: 1.15;
    letter-spacing: -0.025em;
`;

const ShowcaseSubtitle = styled.p`
    margin: 0 0 36px;
    font-size: 16px;
    line-height: 1.65;
    color: #94a3b8;
    max-width: 420px;
`;

const FeatureList = styled.ul`
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 14px;
`;

const FeatureItem = styled.li`
    display: flex;
    align-items: flex-start;
    gap: 12px;
    color: #cbd5e1;
    font-size: 14px;
    line-height: 1.5;

    svg {
        flex-shrink: 0;
        width: 20px;
        height: 20px;
        color: #34d399;
        margin-top: 1px;
    }

    strong {
        display: block;
        color: #f1f5f9;
        font-weight: 600;
        margin-bottom: 2px;
    }
`;

const ShowcaseFooter = styled.div`
    position: relative;
    z-index: 2;
    color: #475569;
    font-size: 12px;
    letter-spacing: 0.04em;
`;

// ===== Right form panel =====
const FormPanel = styled.div`
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 20px;
    position: relative;
    z-index: 1;
`;

const FormCard = styled.div`
    width: 100%;
    max-width: 420px;
    animation: ${slideUp} 0.5s cubic-bezier(0.16, 1, 0.3, 1);
`;

const MobileBrand = styled.div`
    text-align: center;
    margin-bottom: 28px;

    @media (min-width: 1024px) {
        display: none;
    }
`;

const MobileLogo = styled.div`
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.22) 0%, rgba(6, 182, 212, 0.15) 100%);
    border: 1px solid rgba(129, 140, 248, 0.3);
    margin-bottom: 14px;

    svg {
        width: 28px;
        height: 28px;
        color: #c7d2fe;
    }
`;

const MobileBrandName = styled.h1`
    margin: 0 0 4px;
    font-size: 22px;
    font-weight: 700;
    color: #ffffff;
    letter-spacing: -0.01em;
`;

const MobileTagline = styled.p`
    margin: 0;
    font-size: 13px;
    color: #94a3b8;
`;

const DesktopHeader = styled.div`
    display: none;
    margin-bottom: 32px;

    @media (min-width: 1024px) {
        display: block;
    }
`;

const DesktopTitle = styled.h2`
    margin: 0 0 8px;
    font-size: 28px;
    font-weight: 700;
    color: #ffffff;
    letter-spacing: -0.02em;
    line-height: 1.2;
`;

const DesktopSubtitle = styled.p`
    margin: 0;
    font-size: 14px;
    color: #94a3b8;
    line-height: 1.5;
`;

const FormTitle = styled.h3`
    margin: 0 0 20px;
    font-size: 18px;
    font-weight: 600;
    color: #e2e8f0;
    letter-spacing: -0.01em;

    @media (min-width: 1024px) {
        display: none;
    }
`;

const FooterText = styled.p`
    text-align: center;
    margin: 28px 0 0;
    font-size: 11px;
    color: #64748b;
    letter-spacing: 0.03em;
`;

const FormBody = styled.div``;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => (
    <PageShell>
        {/* ===== LEFT: showcase (desktop only) ===== */}
        <ShowcasePanel>
            <GridOverlay />
            <GlowOrb />
            <ShowcaseContent>
                <ShowcaseLogo>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z" />
                    </svg>
                    <span>Cloud Server</span>
                </ShowcaseLogo>
                <ShowcaseHeadline>
                    Kelola server game Anda dengan mudah.
                </ShowcaseHeadline>
                <ShowcaseSubtitle>
                    Panel kontrol modern dengan monitoring real-time,
                    manajemen file bawaan, dan integrasi Telegram Bot untuk
                    auto-deploy server baru dalam hitungan detik.
                </ShowcaseSubtitle>
                <FeatureList>
                    <FeatureItem>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <div>
                            <strong>Auto-Deploy via Telegram</strong>
                            Buat panel baru lewat bot, langsung jalan.
                        </div>
                    </FeatureItem>
                    <FeatureItem>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <div>
                            <strong>Monitoring Real-Time</strong>
                            CPU, RAM, disk — semua terpantau live.
                        </div>
                    </FeatureItem>
                    <FeatureItem>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <div>
                            <strong>Manajemen Akses Granular</strong>
                            Subuser, schedule, backup — semua di satu tempat.
                        </div>
                    </FeatureItem>
                </FeatureList>
            </ShowcaseContent>
            <ShowcaseFooter>
                &copy; {new Date().getFullYear()} Cloud Server Panel
            </ShowcaseFooter>
        </ShowcasePanel>

        {/* ===== RIGHT: form ===== */}
        <FormPanel>
            <FormCard>
                <MobileBrand>
                    <MobileLogo>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z" />
                        </svg>
                    </MobileLogo>
                    <MobileBrandName>Cloud Server</MobileBrandName>
                    <MobileTagline>Game Server Management Panel</MobileTagline>
                </MobileBrand>

                <DesktopHeader>
                    <DesktopTitle>Selamat datang kembali</DesktopTitle>
                    <DesktopSubtitle>Masuk ke akun Anda untuk mengelola server.</DesktopSubtitle>
                </DesktopHeader>

                {title && <FormTitle>{title}</FormTitle>}

                <FlashMessageRender css={tw`mb-4`} />

                <Form {...props} ref={ref}>
                    <FormBody>{props.children}</FormBody>
                </Form>

                <FooterText>
                    &copy; {new Date().getFullYear()}&nbsp;
                    <a rel={'noopener nofollow noreferrer'} href={'#'} target={'_blank'} css={tw`no-underline text-neutral-500 hover:text-neutral-300`}>
                        Cloud Server Panel
                    </a>
                </FooterText>
            </FormCard>
        </FormPanel>
    </PageShell>
));
