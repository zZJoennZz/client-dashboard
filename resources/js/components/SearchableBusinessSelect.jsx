import React, { useEffect, useId, useRef, useState } from 'react';

const defaultInputClass = 'mt-1 min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-white px-3 py-2 text-sm';

export default function SearchableBusinessSelect({
    businesses = [],
    value = '',
    onChange,
    label = 'Business',
    emptyLabel = 'Select a business',
    required = false,
    name,
    inputClassName = defaultInputClass,
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);
    const inputRef = useRef(null);
    const listboxId = useId();
    const selected = businesses.find((business) => String(business.id) === String(value));
    const selectedName = selected?.name ?? selected?.company_name ?? '';
    const filteredBusinesses = businesses.filter((business) => {
        const businessName = business.name ?? business.company_name ?? '';
        const searchable = `${businessName} ${business.city ?? ''} ${business.state ?? ''} ${business.id}`.toLowerCase();

        return searchable.includes(search.trim().toLowerCase());
    });
    const options = [
        { id: '', name: emptyLabel },
        ...filteredBusinesses.map((business) => ({
            id: String(business.id),
            name: business.name ?? business.company_name ?? `Business ${business.id}`,
        })),
    ];

    useEffect(() => {
        inputRef.current?.setCustomValidity(required && !selected ? 'Please select a business.' : '');
    }, [required, selected]);

    function openDropdown() {
        setSearch('');
        const selectedIndex = businesses.findIndex((business) => String(business.id) === String(selected?.id));
        setActiveIndex(selectedIndex >= 0 ? selectedIndex + 1 : businesses.length > 0 ? 1 : 0);
        setIsOpen(true);
    }

    function chooseOption(option) {
        onChange(option.id);
        setIsOpen(false);
        setSearch('');
        setActiveIndex(0);
    }

    function handleKeyDown(event) {
        if (event.key === 'Escape' && isOpen) {
            event.preventDefault();
            setIsOpen(false);
            setSearch('');
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (!isOpen) {
                openDropdown();
                return;
            }

            const direction = event.key === 'ArrowDown' ? 1 : -1;
            setActiveIndex((index) => (index + direction + options.length) % options.length);
        }

        if (event.key === 'Enter' && isOpen && options[activeIndex]) {
            event.preventDefault();
            chooseOption(options[activeIndex]);
        }
    }

    return (
        <div className="relative min-w-0">
            <label className="block text-xs font-semibold">
                {label}
                <input
                    ref={inputRef}
                    type="text"
                    role="combobox"
                    aria-label={label}
                    aria-autocomplete="list"
                    aria-expanded={isOpen}
                    aria-controls={listboxId}
                    aria-activedescendant={isOpen ? `${listboxId}-option-${activeIndex}` : undefined}
                    aria-required={required}
                    autoComplete="off"
                    placeholder={isOpen ? 'Search businesses…' : emptyLabel}
                    required={required}
                    value={isOpen ? search : selectedName}
                    onFocus={openDropdown}
                    onChange={(event) => {
                        const nextSearch = event.target.value.trim().toLowerCase();
                        const hasMatches = businesses.some((business) => {
                            const businessName = business.name ?? business.company_name ?? '';
                            const searchable = `${businessName} ${business.city ?? ''} ${business.state ?? ''} ${business.id}`.toLowerCase();

                            return searchable.includes(nextSearch);
                        });
                        setSearch(event.target.value);
                        setActiveIndex(hasMatches ? 1 : 0);
                        setIsOpen(true);
                    }}
                    onKeyDown={handleKeyDown}
                    onBlur={(event) => {
                        if (!event.currentTarget.parentElement?.contains(event.relatedTarget)) {
                            setIsOpen(false);
                            setSearch('');
                        }
                    }}
                    className={`${inputClassName} pr-9`}
                />
                <svg className="pointer-events-none absolute right-3 bottom-3 size-4 text-[var(--rr-neutral-mid)]" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="m5 7.5 5 5 5-5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </label>
            {name && <input type="hidden" name={name} value={value ?? ''} />}
            {isOpen && (
                <div
                    id={listboxId}
                    role="listbox"
                    aria-label={`${label} options`}
                    className="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-[var(--rr-border)] bg-white py-1 shadow-lg"
                >
                    {options.map((option, index) => (
                        <button
                            key={option.id || 'empty'}
                            id={`${listboxId}-option-${index}`}
                            type="button"
                            tabIndex={-1}
                            role="option"
                            aria-selected={String(value ?? '') === option.id}
                            onMouseDown={(event) => event.preventDefault()}
                            onMouseEnter={() => setActiveIndex(index)}
                            onClick={() => chooseOption(option)}
                            className={`block min-h-10 w-full px-3 py-2 text-left text-sm ${
                                index === activeIndex ? 'bg-[var(--rr-neutral-light)]' : 'bg-white'
                            }`}
                        >
                            {option.name}
                        </button>
                    ))}
                    {filteredBusinesses.length === 0 && (
                        <p className="px-3 py-2 text-sm font-normal text-[var(--rr-neutral-mid)]">No matching businesses.</p>
                    )}
                </div>
            )}
        </div>
    );
}
