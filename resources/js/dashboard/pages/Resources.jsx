
import { useMemo, useState } from "react";
import { Card, EmptyState, PageHeading } from "../components";

export default function Resources({ resources = [] }) {
    const [search, setSearch] = useState("");
    const [selectedType, setSelectedType] = useState("all");
    const [featuredOnly, setFeaturedOnly] = useState(false);

    const resourceTypes = useMemo(() => {
        return [
            ...new Set(
                resources
                    .map((resource) => resource.resource_type)
                    .filter(Boolean)
            ),
        ].sort();
    }, [resources]);

    const filteredResources = useMemo(() => {
        const query = search.trim().toLowerCase();

        return resources.filter((resource) => {
            const matchesSearch =
                !query ||
                [
                    resource.title,
                    resource.description,
                    resource.resource_type,
                    resource.business_name,
                    resource.file_name,
                ].some((value) =>
                    String(value ?? "").toLowerCase().includes(query)
                );

            const matchesType =
                selectedType === "all" ||
                resource.resource_type === selectedType;

            const matchesFeatured =
                !featuredOnly || Boolean(resource.is_featured);

            return matchesSearch && matchesType && matchesFeatured;
        });
    }, [resources, search, selectedType, featuredOnly]);

    return (
        <div className="space-y-6">
            <PageHeading
                title="Resource Library"
                description="Browse practical guides, documents, and other resources to support your business."
            />

            <Card>
                <div className="grid gap-4 md:grid-cols-2">
                    <div>
                        <label
                            htmlFor="resource-search"
                            className="mb-1 block text-sm font-medium"
                        >
                            Search resources
                        </label>
                        <input
                            id="resource-search"
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search by title, keyword, or file..."
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        />
                    </div>

                    <div>
                        <label
                            htmlFor="resource-type"
                            className="mb-1 block text-sm font-medium"
                        >
                            Resource category
                        </label>
                        <select
                            id="resource-type"
                            value={selectedType}
                            onChange={(event) => setSelectedType(event.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        >
                            <option value="all">All categories</option>
                            {resourceTypes.map((type) => (
                                <option key={type} value={type}>
                                    {type.replace(/[_-]/g, " ")}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <label className="mt-4 flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={featuredOnly}
                        onChange={(event) => setFeaturedOnly(event.target.checked)}
                        className="rounded border-gray-300"
                    />
                    Show featured resources only
                </label>

                <p className="mt-3 text-sm text-gray-500" aria-live="polite">
                    {filteredResources.length} of {resources.length} resources
                </p>
            </Card>

            {filteredResources.length === 0 ? (
                <EmptyState
                    title="No resources found"
                    description={
                        resources.length === 0
                            ? "Resources will appear here when they become available."
                            : "Try changing your search or filters."
                    }
                />
            ) : (
                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    {filteredResources.map((resource) => (
                        <Card key={resource.id}>
                            <div className="flex h-full flex-col gap-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    {resource.resource_type && (
                                        <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium capitalize text-gray-700">
                                            {resource.resource_type.replace(/[_-]/g, " ")}
                                        </span>
                                    )}

                                    {resource.is_featured && (
                                        <span className="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">
                                            Featured
                                        </span>
                                    )}
                                </div>

                                <h2 className="text-lg font-semibold text-gray-900">
                                    {resource.title}
                                </h2>

                                {resource.business_name && (
                                    <p className="text-xs text-gray-500">
                                        {resource.business_name}
                                    </p>
                                )}

                                <p className="flex-1 text-sm leading-6 text-gray-600">
                                    {resource.description ||
                                        "No description is available for this resource."}
                                </p>

                                {resource.file_name && (
                                    <p className="break-all text-xs text-gray-500">
                                        File: {resource.file_name}
                                    </p>
                                )}

                                {resource.download_url ? (
                                    <a
                                        href={resource.download_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                    >
                                        Open resource
                                    </a>
                                ) : (
                                    <p className="text-sm text-gray-500">
                                        No resource file is currently available.
                                    </p>
                                )}
                            </div>
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}
