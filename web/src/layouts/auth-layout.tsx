import AuthLayoutTemplate from '@/layouts/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    brandText,
    children,
}: {
    title?: string;
    description?: string;
    brandText?: string;
    children: React.ReactNode;
}) {
    return (
        <AuthLayoutTemplate
            title={title}
            description={description}
            brandText={brandText}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
