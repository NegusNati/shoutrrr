import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import Heading from '@/components/common/heading';
import InputError from '@/components/common/input-error';
import DeleteUser from '@/components/settings/delete-user';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { meQuery, useMeData } from '@/features/me/me';
import { ApiError, apiFetch, webPost } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { fieldString } from '@/lib/forms';
import { cn } from '@/lib/utils';

export default function Profile() {
    const me = useMeData();
    const queryClient = useQueryClient();
    const [selectedPhoto, setSelectedPhoto] = useState<File | null>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [verificationSent, setVerificationSent] = useState(false);

    const user = me?.auth.user;
    const mustVerifyEmail = me?.auth.mustVerifyEmail ?? false;

    useEffect(() => {
        if (!selectedPhoto) {
            setPhotoPreview(null);

            return;
        }

        const previewUrl = URL.createObjectURL(selectedPhoto);
        setPhotoPreview(previewUrl);

        return () => URL.revokeObjectURL(previewUrl);
    }, [selectedPhoto]);

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const data = new FormData(e.currentTarget);
        const body = new FormData();
        body.set('name', fieldString(data, 'name'));
        body.set('email', fieldString(data, 'email'));
        if (selectedPhoto) {
            body.set('photo', selectedPhoto);
        }
        // PHP can't parse multipart PATCH bodies — Laravel's convention is a
        // POST + _method spoof, which resolves to the PATCH route.
        body.set('_method', 'PATCH');

        setProcessing(true);
        setErrors({});

        try {
            await apiFetch(endpoints.settingsProfile, {
                method: 'POST',
                body: body,
            });
            setSelectedPhoto(null);
            await queryClient.invalidateQueries({
                queryKey: meQuery.queryKey,
            });
        } catch (err) {
            setErrors(
                err instanceof ApiError
                    ? Object.fromEntries(
                          Object.entries(err.errors).map(([k, v]) => [k, v[0]]),
                      )
                    : { _: 'Failed to update profile' },
            );
        } finally {
            setProcessing(false);
        }
    };

    const resendVerification = async () => {
        try {
            await webPost('/email/verification-notification');
            setVerificationSent(true);
        } catch {
            setVerificationSent(false);
        }
    };

    if (!user) {
        return null;
    }

    return (
        <>
            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Update your name and email address"
                />

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="profile-photo">Profile photo</Label>
                        <div className="flex items-center gap-4">
                            <Avatar className="size-16">
                                <AvatarImage
                                    src={photoPreview ?? user.avatar}
                                    alt={user.name}
                                />
                            </Avatar>
                            <div className="grid min-w-0 gap-1">
                                <label
                                    htmlFor="profile-photo"
                                    aria-disabled={processing}
                                    className={cn(
                                        buttonVariants(),
                                        'w-fit cursor-pointer aria-disabled:pointer-events-none aria-disabled:opacity-50',
                                    )}
                                >
                                    Choose photo
                                </label>
                                <Input
                                    id="profile-photo"
                                    type="file"
                                    name="photo"
                                    accept="image/*"
                                    disabled={processing}
                                    className="sr-only"
                                    onChange={(event) =>
                                        setSelectedPhoto(
                                            event.currentTarget.files?.[0] ??
                                                null,
                                        )
                                    }
                                />
                                <p
                                    className="max-w-56 truncate text-xs text-muted-foreground"
                                    title={selectedPhoto?.name}
                                >
                                    {selectedPhoto
                                        ? `Selected: ${selectedPhoto.name}`
                                        : 'Image up to 2 MB.'}
                                </p>
                            </div>
                        </div>
                        <InputError className="mt-2" message={errors.photo} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>

                        <Input
                            id="name"
                            className="mt-1 block w-full"
                            defaultValue={user.name}
                            name="name"
                            required
                            autoComplete="name"
                            placeholder="Full name"
                        />

                        <InputError className="mt-2" message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>

                        <Input
                            id="email"
                            type="email"
                            className="mt-1 block w-full"
                            defaultValue={user.email}
                            name="email"
                            required
                            autoComplete="username"
                            placeholder="Email address"
                        />

                        <InputError className="mt-2" message={errors.email} />
                    </div>

                    {mustVerifyEmail && user.email_verified_at === null && (
                        <div>
                            <p className="-mt-4 text-sm text-muted-foreground">
                                Your email address is unverified.{' '}
                                <button
                                    type="button"
                                    onClick={() => void resendVerification()}
                                    className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                >
                                    Click here to re-send the verification
                                    email.
                                </button>
                            </p>

                            {verificationSent && (
                                <div className="mt-2 text-sm font-medium text-green-600">
                                    A new verification link has been sent to
                                    your email address.
                                </div>
                            )}
                        </div>
                    )}

                    <div className="flex items-center gap-4">
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="update-profile-button"
                        >
                            Save
                        </Button>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </>
    );
}
