import { useMutation } from '@tanstack/react-query';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useState, type FormEvent } from 'react';
import { toast } from 'sonner';

import Heading from '@/components/common/heading';
import InputError from '@/components/common/input-error';
import DeleteUser from '@/components/settings/delete-user';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { resendVerificationEmail } from '@/features/auth/auth';
import { useInvalidateMe, useMeData } from '@/features/me/me';
import {
    profileQuery,
    updateProfile,
} from '@/features/user-settings/user-settings';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { ApiError, errorMessage } from '@/lib/api';
import { fieldString } from '@/lib/forms';
import { cn } from '@/lib/utils';

export default function ProfilePage() {
    useDocumentTitle('Profile settings');

    const { data } = useQuery(profileQuery);
    const me = useMeData();
    const user = me?.auth.user;
    const invalidateMe = useInvalidateMe();

    const [selectedPhoto, setSelectedPhoto] = useState<File | null>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const [verificationSent, setVerificationSent] = useState(false);
    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );

    useEffect(() => {
        if (!selectedPhoto) {
            setPhotoPreview(null);

            return;
        }

        const previewUrl = URL.createObjectURL(selectedPhoto);
        setPhotoPreview(previewUrl);

        return () => URL.revokeObjectURL(previewUrl);
    }, [selectedPhoto]);

    const save = useMutation({
        mutationFn: updateProfile,
        onSuccess: () => {
            setErrors({});
            setSelectedPhoto(null);
            void invalidateMe();
            toast.success('Profile updated.');
        },
        onError: (error) => {
            if (error instanceof ApiError && error.status === 422) {
                setErrors({
                    name: error.fieldError('name'),
                    email: error.fieldError('email'),
                    photo: error.fieldError('photo'),
                });
            } else {
                toast.error(
                    errorMessage(error, 'Could not update your profile.'),
                );
            }
        },
    });

    const resend = useMutation({
        mutationFn: resendVerificationEmail,
        onSuccess: () => setVerificationSent(true),
        onError: (error) =>
            toast.error(
                errorMessage(error, 'Could not send the verification email.'),
            ),
    });

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        save.mutate({
            name: fieldString(form, 'name'),
            email: fieldString(form, 'email'),
            photo: selectedPhoto,
        });
    }

    const mustVerifyEmail = data?.mustVerifyEmail ?? false;

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
                                    src={photoPreview ?? user?.avatar}
                                    alt={user?.name}
                                />
                            </Avatar>
                            <div className="grid min-w-0 gap-1">
                                <label
                                    htmlFor="profile-photo"
                                    aria-disabled={save.isPending}
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
                                    disabled={save.isPending}
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
                            defaultValue={user?.name}
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
                            defaultValue={user?.email}
                            name="email"
                            required
                            autoComplete="username"
                            placeholder="Email address"
                        />

                        <InputError className="mt-2" message={errors.email} />
                    </div>

                    {mustVerifyEmail && user?.email_verified_at === null && (
                        <div>
                            <p className="-mt-4 text-sm text-muted-foreground">
                                Your email address is unverified.{' '}
                                <button
                                    type="button"
                                    className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                    disabled={resend.isPending}
                                    onClick={() => resend.mutate()}
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
                            disabled={save.isPending}
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
