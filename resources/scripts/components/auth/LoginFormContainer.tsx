import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled, { keyframes } from 'styled-components/macro';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
};

/**
 * Cloud Server — Modern Login Container
 *
 * Design language:
 *  - Glassmorphism card with gradient border + soft shadow.
 *  - Animated aurora background blobs (indigo + cyan + purple).
 *  - Centered cloud icon with glow effect.
 *  - Modern title + subtitle typography.
 *  - Responsive: card shrinks on mobile, blobs stay subtle.
 */

const fadeInUp = keyframes`
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
`;

const auroraShift = keyframes`
    0%   { transform: translate(0%, 0%) scale(1); opacity: 0.45; }
    50%  { transform: translate(20%, 30%) scale(1.2); opacity: 0.65; }
    100% { transform: translate(0%, 0%) scale(1); opacity: 0.45; }
`;

const pulse = keyframes`
    0%, 100% { opacity: 1; transform: scale(1); }
    50%      { opacity: 0.7; transform: scale(1.05); }
`;

const Container = styled.div`
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    background:
        radial-gradient(circle at 15% 20%, rgba(99, 102, 241, 0.25) 0%, transparent 50%),
        radial-gradient(circle at 85% 80%, rgba(6, 182, 212, 0.18) 0%, transparent 50%),
        linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    overflow: hidden;
`;

const AuroraBlob1 = styled.div`
    position: absolute;
    top: -10%;
    left: 60%;
    width: 420px;
    height: 420px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.5) 0%, transparent 70%);
    filter: blur(40px);
    animation: ${auroraShift} 14s ease-in-out infinite;
    pointer-events: none;
    z-index: 0;
`;

const AuroraBlob2 = styled.div`
    position: absolute;
    top: 50%;
    left: -15%;
    width: 360px;
    height: 360px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(6, 182, 212, 0.4) 0%, transparent 70%);
    filter: blur(40px);
    animation: ${auroraShift} 14s ease-in-out infinite;
    animation-delay: 4s;
    pointer-events: none;
    z-index: 0;
`;

const AuroraBlob3 = styled.div`
    position: absolute;
    top: 70%;
    left: 70%;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.3) 0%, transparent 70%);
    filter: blur(40px);
    animation: ${auroraShift} 14s ease-in-out infinite;
    animation-delay: 7s;
    pointer-events: none;
    z-index: 0;
`;

const Card = styled.div`
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 460px;
    padding: 40px 36px 32px;
    border-radius: 22px;
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.92) 0%, rgba(15, 23, 42, 0.96) 100%);
    border: 1px solid rgba(129, 140, 248, 0.2);
    box-shadow:
        0 24px 60px -16px rgba(0, 0, 0, 0.65),
        0 0 0 1px rgba(255, 255, 255, 0.04),
        inset 0 1px 0 rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(20px);
    animation: ${fadeInUp} 0.5s cubic-bezier(0.16, 1, 0.3, 1);

    @media (max-width: 480px) {
        padding: 28px 22px 22px;
        border-radius: 18px;
    }
`;

const BrandBlock = styled.div`
    text-align: center;
    margin-bottom: 24px;
`;

const CloudIconWrap = styled.div`
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.25) 0%, rgba(6, 182, 212, 0.18) 100%);
    border: 1px solid rgba(129, 140, 248, 0.35);
    margin-bottom: 14px;
    box-shadow: 0 8px 24px -8px rgba(99, 102, 241, 0.5);

    svg {
        width: 32px;
        height: 32px;
        color: #c7d2fe;
        animation: ${pulse} 3s ease-in-out infinite;
    }
`;

const BrandTitle = styled.h1`
    margin: 0 0 4px;
    font-size: 22px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.01em;
`;

const BrandSubtitle = styled.p`
    margin: 0;
    font-size: 13px;
    color: #94a3b8;
    letter-spacing: 0.01em;
`;

const FormTitle = styled.h2`
    margin: 0 0 16px;
    font-size: 18px;
    font-weight: 600;
    color: #e2e8f0;
    text-align: center;
    letter-spacing: -0.01em;
`;

const FooterText = styled.p`
    text-align: center;
    margin: 24px 0 0;
    font-size: 11px;
    color: #64748b;
    letter-spacing: 0.02em;
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => (
    <Container>
        <AuroraBlob1 />
        <AuroraBlob2 />
        <AuroraBlob3 />
        <Card>
            <BrandBlock>
                <CloudIconWrap>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z" />
                    </svg>
                </CloudIconWrap>
                <BrandTitle>Cloud Server</BrandTitle>
                <BrandSubtitle>Game Server Management Panel</BrandSubtitle>
            </BrandBlock>

            {title && <FormTitle>{title}</FormTitle>}

            <FlashMessageRender css={tw`mb-4`} />

            <Form {...props} ref={ref}>
                {props.children}
            </Form>

            <FooterText>
                &copy; {new Date().getFullYear()}&nbsp;
                <a rel={'noopener nofollow noreferrer'} href={'#'} target={'_blank'} css={tw`no-underline text-neutral-500 hover:text-neutral-300`}>
                    Cloud Server Panel
                </a>
            </FooterText>
        </Card>
    </Container>
));
