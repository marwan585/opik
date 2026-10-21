import React, { useState } from 'react';
import { RouteComponentProps } from 'react-router';
import { Link } from 'react-router-dom';
import performPasswordReset from '@/api/auth/performPasswordReset';
import { httpErrorToHuman } from '@/api/http';
import LoginFormContainer from '@/components/auth/LoginFormContainer';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import { Formik, FormikHelpers } from 'formik';
import { object, ref, string } from 'yup';
import Field from '@/components/elements/Field';
import Input from '@/components/elements/Input';
import tw from 'twin.macro';
import styled from 'styled-components/macro';

interface Values {
    password: string;
    passwordConfirmation: string;
}

const InputGroup = styled.div`
    margin-bottom: 18px;

    label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 7px;
    }

    input {
        width: 100%;
        padding: 12px 14px;
        font-size: 14px;
        color: #f1f5f9;
        background: rgba(15, 23, 42, 0.5);
        border: 1px solid rgba(100, 116, 139, 0.25);
        border-radius: 10px;
        outline: none;
        transition: all 0.18s ease;
        font-family: inherit;

        &::placeholder { color: #64748b; }
        &:hover:not(:disabled) { border-color: rgba(129, 140, 248, 0.35); background: rgba(15, 23, 42, 0.7); }
        &:focus { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15); background: rgba(15, 23, 42, 0.85); }
        &:disabled { opacity: 0.6; cursor: not-allowed; }
    }

    .input-help.error { margin-top: 7px; font-size: 11px; color: #fca5a5; font-weight: 500; }
    .input-help { margin-top: 7px; font-size: 11px; color: #64748b; }
`;

const EmailRow = styled.div`
    margin-bottom: 18px;

    label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 7px;
    }

    input {
        width: 100%;
        padding: 12px 14px;
        font-size: 14px;
        color: #94a3b8;
        background: rgba(15, 23, 42, 0.3);
        border: 1px solid rgba(100, 116, 139, 0.15);
        border-radius: 10px;
        outline: none;
        cursor: not-allowed;
        font-family: inherit;
    }
`;

const SubmitButton = styled.button`
    width: 100%;
    padding: 13px 20px;
    margin-top: 4px;
    font-size: 14px;
    font-weight: 600;
    color: #ffffff;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 8px 20px -8px rgba(79, 70, 229, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.15);
    letter-spacing: 0.02em;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;

    &:hover:not(:disabled) {
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        box-shadow: 0 12px 28px -8px rgba(79, 70, 229, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.2);
        transform: translateY(-1px);
    }
    &:active:not(:disabled) { transform: translateY(0); }
    &:disabled { opacity: 0.75; cursor: not-allowed; }
`;

const SpinnerWrap = styled.div`
    display: inline-block;
    width: 15px;
    height: 15px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    @keyframes spin { to { transform: rotate(360deg); } }
`;

const BottomRow = styled.div`
    display: flex;
    justify-content: center;
    margin-top: 18px;

    a {
        font-size: 12px;
        color: #818cf8;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.15s ease;
    }
    a:hover { color: #a5b4fc; text-decoration: underline; }
`;

export default ({ match, location }: RouteComponentProps<{ token: string }>) => {
    const [email, setEmail] = useState('');

    const { clearFlashes, addFlash } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);

    const parsed = new URLSearchParams(location.search);
    if (email.length === 0 && parsed.get('email')) {
        setEmail(parsed.get('email') || '');
    }

    const submit = ({ password, passwordConfirmation }: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes();
        performPasswordReset(email, { token: match.params.token, password, passwordConfirmation })
            .then(() => {
                // @ts-expect-error this is valid
                window.location = '/';
            })
            .catch((error) => {
                console.error(error);
                setSubmitting(false);
                addFlash({ type: 'error', title: 'Error', message: httpErrorToHuman(error) });
            });
    };

    return (
        <Formik
            onSubmit={submit}
            initialValues={{ password: '', passwordConfirmation: '' }}
            validationSchema={object().shape({
                password: string()
                    .required('Password baru harus diisi.')
                    .min(8, 'Password minimal 8 karakter.'),
                passwordConfirmation: string()
                    .required('Konfirmasi password tidak cocok.')
                    // @ts-expect-error this is valid
                    .oneOf([ref('password'), null], 'Konfirmasi password tidak cocok.'),
            })}
        >
            {({ isSubmitting }) => (
                <LoginFormContainer title={'Reset Password'} css={tw`w-full flex`}>
                    <EmailRow>
                        <label>Email</label>
                        <Input value={email} isLight disabled />
                    </EmailRow>
                    <InputGroup>
                        <Field
                            label={'Password Baru'}
                            name={'password'}
                            type={'password'}
                            description={'Password minimal 8 karakter.'}
                            placeholder={'••••••••'}
                        />
                    </InputGroup>
                    <InputGroup>
                        <Field
                            label={'Konfirmasi Password Baru'}
                            name={'passwordConfirmation'}
                            type={'password'}
                            placeholder={'••••••••'}
                        />
                    </InputGroup>
                    <div css={tw`mt-2`}>
                        <SubmitButton type={'submit'} disabled={isSubmitting}>
                            {isSubmitting ? (
                                <>
                                    <SpinnerWrap />
                                    <span>Memproses...</span>
                                </>
                            ) : (
                                <span>Reset Password</span>
                            )}
                        </SubmitButton>
                    </div>
                    <BottomRow>
                        <Link to={'/auth/login'}>← Kembali ke Login</Link>
                    </BottomRow>
                </LoginFormContainer>
            )}
        </Formik>
    );
};
