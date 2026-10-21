import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled, { keyframes } from 'styled-components/macro';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
};

/**
 * Cloud Server — Modern Centered Login Container
 *
 * Single centered card on dark gradient background.
 * Everything (brand, title, inputs, button, footer) is centered.
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
    0%, 100% { opacity: 0.4; transform: scale(1); }
    50%      { opacity: 0.7; transform: scale(1.1); }
`;

const PageShell = styled.div`
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    background:
        radial-gradient(ellipse at top, rgba(99, 102, 241, 0.18) 0%, transparent 60%),
        radial-gradient(ellipse at bottom, rgba(6, 182, 212, 0.12) 0%, transparent 60%),
        linear-gradient(135deg, #0b1020 0%, #0f172a 50%, #0b1020 100%);
    overflow: hidden;
    animation: ${fadeIn} 0.4s ease-out;
`;

const GlowOrb1 = styled.div`
    position: absolute;
    top: -15%;
    left: 50%;
    transform: translateX(-50%);
    width: 600px;
    height: 600px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, transparent 70%);
    filter: blur(80px);
    animation: ${glowPulse} 8s ease-in-out infinite;
    pointer-events: none;
    z-index: 0;
`;

const GlowOrb2 = styled.div`
    position: absolute;
    bottom: -15%;
    right: 10%;
    width: 400px;
    height: 400px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.2) 0%, transparent 70%);
    filter: blur(80px);
    animation: ${glowPulse} 10s ease-in-out infinite;
    animation-delay: 3s;
    pointer-events: none;
    z-index: 0;
`;

const Card = styled.div`
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 420px;
    padding: 40px 36px 28px;
    border-radius: 20px;
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.85) 0%, rgba(15, 23, 42, 0.95) 100%);
    border: 1px solid rgba(129, 140, 248, 0.18);
    box-shadow:
        0 24px 60px -16px rgba(0, 0, 0, 0.6),
        0 0 0 1px rgba(255, 255, 255, 0.04),
        inset 0 1px 0 rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(20px);
    animation: ${slideUp} 0.5s cubic-bezier(0.16, 1, 0.3, 1);
    text-align: center;

    @media (max-width: 480px) {
        padding: 32px 22px 24px;
        border-radius: 16px;
    }
`;

const BrandBlock = styled.div`
    margin-bottom: 28px;
`;

const CloudIconWrap = styled.div`
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.22) 0%, rgba(6, 182, 212, 0.15) 100%);
    border: 1px solid rgba(129, 140, 248, 0.32);
    margin-bottom: 14px;
    box-shadow: 0 8px 24px -8px rgba(99, 102, 241, 0.5);

    svg {
        width: 28px;
        height: 28px;
        color: #c7d2fe;
    }
`;

const BrandName = styled.h1`
    margin: 0 0 4px;
    font-size: 22px;
    font-weight: 700;
    color: #ffffff;
    letter-spacing: -0.01em;
`;

const BrandTagline = styled.p`
    margin: 0;
    font-size: 13px;
    color: #94a3b8;
    letter-spacing: 0.01em;
`;

const FormTitle = styled.h2`
    margin: 0 0 6px;
    font-size: 18px;
    font-weight: 600;
    color: #e2e8f0;
    letter-spacing: -0.01em;
`;

const FormSubtitle = styled.p`
    margin: 0 0 24px;
    font-size: 13px;
    color: #64748b;
`;

const FormBody = styled.div`
    text-align: left;
`;

const FooterText = styled.p`
    margin: 26px 0 0;
    font-size: 11px;
    color: #64748b;
    letter-spacing: 0.03em;
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => (
    <PageShell>
        <GlowOrb1 />
        <GlowOrb2 />
        <Card>
            <BrandBlock>
                <CloudIconWrap>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z" />
                    </svg>
                </CloudIconWrap>
                <BrandName>Cloud Server</BrandName>
                <BrandTagline>Game Server Management Panel</BrandTagline>
            </BrandBlock>

            {title && <FormTitle>{title}</FormTitle>}
            <FormSubtitle>Masuk untuk mengelola server Anda</FormSubtitle>

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
        </Card>
    </PageShell>
));
