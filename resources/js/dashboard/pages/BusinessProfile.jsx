import React, { useEffect, useState } from 'react';
import { Card, Definition, EmptyState, PageHeading, Status } from '../components/UI';

const inputClass = 'min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-white px-3 py-2 text-sm text-[var(--rr-neutral-dark)] focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20';

async function request(url, method, body) {
    const multipart = body instanceof FormData;
    const response = await fetch(url, {
        method,
        body: multipart ? body : JSON.stringify(body),
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            ...(!multipart ? { 'Content-Type': 'application/json' } : {}),
        },
    });
    const payload = response.status === 204 ? null : await response.json().catch(() => ({}));
    if (!response.ok) {
        const errors = Object.values(payload?.errors ?? {}).flat();
        throw new Error(errors.join(' ') || payload?.message || 'The business profile could not be saved.');
    }
    return payload;
}

function ProfileField({ label, name, value, type = 'text', multiline = false }) {
    const controlClass = `${inputClass} mt-1`;
    return (
        <label className="block text-xs font-semibold text-[var(--rr-neutral-dark)]">
            {label}
            {multiline
                ? <textarea name={name} defaultValue={value ?? ''} rows="4" className={controlClass} />
                : <input name={name} type={type} defaultValue={value ?? ''} className={controlClass} />}
        </label>
    );
}

function BusinessCategoryFields({ business, categoryOptions }) {
    const additionalCategories = (business.categories ?? []).filter((category) => category !== business.category);
    const isKnownPrimary = categoryOptions.includes(business.category);
    const [primaryChoice, setPrimaryChoice] = useState(isKnownPrimary ? business.category : 'Other');
    const [customPrimary, setCustomPrimary] = useState(isKnownPrimary ? '' : business.category ?? '');
    const [selectedAdditional, setSelectedAdditional] = useState(
        additionalCategories.filter((category) => categoryOptions.includes(category)),
    );
    const [customAdditional, setCustomAdditional] = useState(
        additionalCategories.filter((category) => !categoryOptions.includes(category)).join(', '),
    );

    function updatePrimary(event) {
        const category = event.target.value;
        setPrimaryChoice(category);
        if (category !== 'Other') {
            setCustomPrimary('');
            setSelectedAdditional((current) => current.filter((item) => item !== category));
        }
    }

    return (
        <>
            <label className="block text-xs font-semibold text-[var(--rr-neutral-dark)]">
                Primary category
                <select required value={primaryChoice} onChange={updatePrimary} className={`${inputClass} mt-1`}>
                    <option value="">Choose a category</option>
                    {categoryOptions.map((category) => <option key={category} value={category}>{category}</option>)}
                    <option value="Other">Other</option>
                </select>
                <input type="hidden" name="category" value={primaryChoice === 'Other' ? customPrimary : primaryChoice} />
            </label>
            {primaryChoice === 'Other' && (
                <label className="block text-xs font-semibold text-[var(--rr-neutral-dark)]">
                    Your business category
                    <input
                        required
                        maxLength="100"
                        value={customPrimary}
                        onChange={(event) => setCustomPrimary(event.target.value)}
                        className={`${inputClass} mt-1`}
                    />
                </label>
            )}
            <fieldset className="sm:col-span-2">
                <legend className="text-xs font-semibold text-[var(--rr-neutral-dark)]">Additional categories</legend>
                <p className="mt-1 text-xs text-[var(--rr-neutral-mid)]">Choose any that apply. Add custom categories below if needed.</p>
                <div className="mt-2 grid gap-2 sm:grid-cols-2">
                    {categoryOptions.filter((category) => category !== primaryChoice).map((category) => (
                        <label key={category} className="flex min-h-9 items-center gap-2 text-sm font-normal">
                            <input
                                type="checkbox"
                                name="additional_categories"
                                value={category}
                                checked={selectedAdditional.includes(category)}
                                onChange={(event) => setSelectedAdditional((current) => (
                                    event.target.checked
                                        ? [...current, category]
                                        : current.filter((item) => item !== category)
                                ))}
                                className="size-4 accent-[var(--rr-primary)]"
                            />
                            {category}
                        </label>
                    ))}
                </div>
                <label className="mt-3 block text-xs font-semibold text-[var(--rr-neutral-dark)]">
                    Other additional categories (comma separated)
                    <input
                        name="custom_additional_categories"
                        value={customAdditional}
                        onChange={(event) => setCustomAdditional(event.target.value)}
                        className={`${inputClass} mt-1`}
                    />
                </label>
            </fieldset>
        </>
    );
}

export default function BusinessProfile({ data, onRefresh }) {
    const business = data.business;
    const [notice, setNotice] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [formKey, setFormKey] = useState(0);

    useEffect(() => {
        setFormKey((key) => key + 1);
    }, [business]);

    if (data.user.is_super_admin) {
        return (
            <div>
                <PageHeading eyebrow="Super Administrator" title="Business profiles">
                    Business profile details and directory status across all businesses.
                </PageHeading>
                <Card>
                    {data.businesses.length === 0 ? (
                        <EmptyState title="No businesses have been created">Business profiles will appear here when businesses are added.</EmptyState>
                    ) : (
                        <div className="divide-y divide-[var(--rr-border)]">
                            {data.businesses.map((item) => (
                                <article key={item.id} className="grid gap-4 py-5 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                                    <div>
                                        <h2 className="font-semibold">{item.name}</h2>
                                        <p className="mt-1 text-sm text-[var(--rr-neutral-mid)]">{[item.category, item.city, item.state].filter(Boolean).join(' · ') || 'Business details not provided'}</p>
                                        <p className="mt-2 text-sm text-[var(--rr-neutral-mid)]">{item.contact_name || 'Contact not provided'}{item.contact_email ? ` · ${item.contact_email}` : ''}</p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Status tone={item.directory_status === 'Published' ? 'success' : 'neutral'}>{item.directory_status}</Status>
                                        <Status tone={item.membership?.status === 'active' ? 'success' : 'neutral'}>{item.membership?.status ?? 'No membership'}</Status>
                                        <Status tone={item.is_active ? 'success' : 'neutral'}>{item.is_active ? 'Active' : 'Inactive'}</Status>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
        );
    }

    return (
        <div>
            <PageHeading eyebrow="Your business" title="Business profile">
                Update your business details and manage whether your profile appears in the public business directory.
            </PageHeading>
            {!business ? (
                <Card><EmptyState title="No business profile is connected">There is no active business association for this account.</EmptyState></Card>
            ) : (
                <>
                    {notice && <p role="status" className="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900">{notice}</p>}
                    {error && <p role="alert" className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</p>}
                    <div className="grid gap-5 lg:grid-cols-3">
                        <Card eyebrow="Your business" title={business.name} className="lg:col-span-2">
                            {business.can_manage_profile ? (
                                <form key={formKey} onSubmit={async (event) => {
                                    event.preventDefault();
                                    setSaving(true);
                                    setError('');
                                    setNotice('');
                                    const form = new FormData(event.currentTarget);
                                    const payload = Object.fromEntries(form.entries());
                                    payload.additional_categories = [
                                        ...form.getAll('additional_categories'),
                                        ...String(form.get('custom_additional_categories') || '').split(','),
                                    ].map((item) => item.trim()).filter(Boolean);
                                    delete payload.custom_additional_categories;
                                    for (const field of ['specialties', 'appointment_methods', 'service_areas']) {
                                        payload[field] = (payload[field] || '').split(',').map((item) => item.trim()).filter(Boolean);
                                    }
                                    try {
                                        await request(`/dashboard/businesses/${business.id}/profile`, 'PUT', payload);
                                        await onRefresh();
                                        setNotice('Business profile saved.');
                                    } catch (requestError) {
                                        setError(requestError.message);
                                    } finally {
                                        setSaving(false);
                                    }
                                }} className="mt-5 grid gap-4 sm:grid-cols-2">
                                    <ProfileField label="Business name" name="company_name" value={business.name} />
                                    <BusinessCategoryFields business={business} categoryOptions={data.business_category_options ?? []} />
                                    <ProfileField label="Tagline" name="tagline" value={business.tagline} />
                                    <ProfileField label="Short description" name="short_description" value={business.short_description} multiline />
                                    <ProfileField label="Description" name="description" value={business.description} multiline />
                                    <ProfileField label="Contact name" name="contact_name" value={business.contact_name} />
                                    <ProfileField label="Contact email" name="contact_email" type="email" value={business.contact_email} />
                                    <ProfileField label="Contact phone" name="contact_phone" value={business.contact_phone} />
                                    <ProfileField label="Alternate phone" name="alternate_phone" value={business.alternate_phone} />
                                    <ProfileField label="Website" name="website" type="url" value={business.website} />
                                    <ProfileField label="Address" name="address_line_1" value={business.address_line_1} />
                                    <ProfileField label="Address line 2" name="address_line_2" value={business.address_line_2} />
                                    <ProfileField label="City" name="city" value={business.city} />
                                    <ProfileField label="State / region" name="state" value={business.state} />
                                    <ProfileField label="Postal code" name="postal_code" value={business.postal_code} />
                                    <ProfileField label="Country code" name="country" value={business.country} />
                                    <ProfileField label="Facebook URL" name="facebook_url" type="url" value={business.facebook_url} />
                                    <ProfileField label="Instagram URL" name="instagram_url" type="url" value={business.instagram_url} />
                                    <ProfileField label="LinkedIn URL" name="linkedin_url" type="url" value={business.linkedin_url} />
                                    <ProfileField label="TikTok URL" name="tiktok_url" type="url" value={business.tiktok_url} />
                                    <ProfileField label="YouTube URL" name="youtube_url" type="url" value={business.youtube_url} />
                                    <ProfileField label="Other social URL" name="other_social_url" type="url" value={business.other_social_url} />
                                    <ProfileField label="Specialties (comma separated)" name="specialties" value={(business.specialties ?? []).join(', ')} />
                                    <ProfileField label="Appointment methods (comma separated)" name="appointment_methods" value={(business.appointment_methods ?? []).join(', ')} />
                                    <ProfileField label="Service areas (comma separated)" name="service_areas" value={(business.service_areas ?? []).join(', ')} />
                                    <ProfileField label="Accessibility information" name="accessibility_information" value={business.accessibility_information} multiline />
                                    <div className="flex flex-wrap items-center gap-3 sm:col-span-2">
                                        <button disabled={saving} className="min-h-11 rounded-lg bg-[var(--rr-primary)] px-5 text-sm font-semibold text-white hover:bg-[var(--rr-primary-light)] disabled:opacity-50">
                                            {saving ? 'Saving…' : 'Save business profile'}
                                        </button>
                                        <span className="text-xs text-[var(--rr-neutral-mid)]">Your changes are saved to your business only.</span>
                                    </div>
                                </form>
                            ) : (
                                <>
                                    <p className="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">{business.description || 'No business description has been added.'}</p>
                                    <dl className="mt-6 grid gap-5 border-t border-[var(--rr-border)] pt-5 sm:grid-cols-2">
                                        <Definition label="Category" value={business.category} />
                                        <Definition label="Website" value={business.website} />
                                        <Definition label="Address" value={business.address_line_1} />
                                        <Definition label="Address line 2" value={business.address_line_2} />
                                        <Definition label="City" value={business.city} />
                                        <Definition label="State" value={business.state} />
                                        <Definition label="Postal code" value={business.postal_code} />
                                        <Definition label="Account role" value={business.role} />
                                    </dl>
                                    <p className="mt-5 text-sm text-[var(--rr-neutral-mid)]">Only business owners and administrators can edit or publish this profile. Ask an owner to update your access if needed.</p>
                                </>
                            )}
                        </Card>
                        <div className="space-y-5">
                            {business.can_manage_profile && <Card eyebrow="Directory photos" title="Business gallery">
                                <p className="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">{business.image_count} of 10 photos. At least two photos are required before the profile can be published.</p>
                                {business.images?.length > 0 && <div className="mt-4 grid grid-cols-2 gap-2">
                                    {business.images.map((image) => <img key={image.id} src={image.url} alt={image.alt_text || ''} className="aspect-square w-full rounded-lg object-cover" />)}
                                </div>}
                                <form onSubmit={async (event) => {
                                    event.preventDefault();
                                    const formElement = event.currentTarget;
                                    setUploading(true);
                                    setError('');
                                    setNotice('');
                                    const formData = new FormData();
                                    for (const file of formElement.elements.images.files) formData.append('images[]', file);
                                    formData.append('alt_text[]', formElement.elements.alt_text.value);
                                    try {
                                        const result = await request(`/dashboard/businesses/${business.id}/images`, 'POST', formData);
                                        await onRefresh();
                                        setNotice(`${result.image_count} business photos saved.`);
                                        formElement.reset();
                                    } catch (requestError) {
                                        setError(requestError.message);
                                    } finally {
                                        setUploading(false);
                                    }
                                }} className="mt-4 space-y-3">
                                    <label className="block text-xs font-semibold">Add photos (JPEG, PNG, or WebP; maximum 10 MB each)
                                        <input name="images" type="file" accept="image/jpeg,image/png,image/webp" multiple required className={`${inputClass} mt-1`} />
                                    </label>
                                    <label className="block text-xs font-semibold">Alt text for these photos
                                        <input name="alt_text" maxLength="255" className={`${inputClass} mt-1`} />
                                    </label>
                                    <button disabled={uploading} className="min-h-11 rounded-lg border border-[var(--rr-border)] px-4 text-sm font-semibold text-[var(--rr-primary)] disabled:opacity-50">{uploading ? 'Uploading…' : 'Upload photos'}</button>
                                </form>
                            </Card>}
                            <Card eyebrow="Directory" title="Publication status">
                                <div className="mt-3"><Status tone={business.is_published ? 'success' : 'neutral'}>{business.is_published ? 'Published publicly' : 'Not published'}</Status></div>
                                <p className="mt-3 text-sm leading-6 text-[var(--rr-neutral-mid)]">{business.is_published ? 'Your business profile is visible in the public directory.' : 'When published, your business profile can be found in the public directory.'}</p>
                                {business.public_url && <a href={business.public_url} target="_blank" rel="noreferrer" className="mt-3 inline-flex min-h-11 items-center text-sm font-semibold text-[var(--rr-primary)] underline">View public profile</a>}
                                {business.can_manage_profile && <button type="button" onClick={async () => {
                                    setError('');
                                    setNotice('');
                                    if (!business.is_published && !window.confirm('Publish your business profile to the public directory?')) return;
                                    try {
                                        await request(`/dashboard/businesses/${business.id}/publication`, 'PATCH', { is_published: !business.is_published });
                                        await onRefresh();
                                        setNotice(business.is_published ? 'Your business profile is now unpublished.' : 'Your business profile is now public.');
                                    } catch (requestError) {
                                        setError(requestError.message);
                                    }
                                }} className={`mt-4 min-h-11 w-full rounded-lg px-4 text-sm font-semibold ${business.is_published ? 'border border-[var(--rr-border)] text-[var(--rr-neutral-dark)]' : 'bg-[var(--rr-highlight)] text-white hover:bg-[var(--rr-highlight-hover)]'}`}>
                                    {business.is_published ? 'Unpublish profile' : 'Publish profile'}
                                </button>}
                            </Card>
                            <Card eyebrow="Contact" title="Business contact">
                                <dl className="mt-4 space-y-4">
                                    <Definition label="Contact name" value={business.contact_name} />
                                    <Definition label="Email" value={business.contact_email} />
                                    <Definition label="Phone" value={business.contact_phone} />
                                    <Definition label="Your role" value={business.role} />
                                </dl>
                            </Card>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
