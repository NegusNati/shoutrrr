/**
 * Read a text field out of a submitted FormData. `FormData.get` returns
 * `FormDataEntryValue | null` (string | File | null), so `form.get(x) as string`
 * is a lying cast — this verifies instead. Missing/file values come back as '',
 * letting the server's field validation produce the error rather than
 * fabricating a string that was never typed.
 */
export function fieldString(form: FormData, name: string): string {
    const value = form.get(name);

    return typeof value === 'string' ? value : '';
}
