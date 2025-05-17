<?php
// /shared/templates/admin-header.php
?>
<div class="search-container">
    <form action="/admin/search.php" method="get" class="search-form">
        <div class="input-group">
            <input 
                type="text" 
                id="global-search" 
                name="q" 
                class="form-control" 
                placeholder="Search or enter code (TKT-1234)" 
                autocomplete="off"
            >
            <div class="input-group-append">
                <button class="btn btn-primary" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </form>
    <div id="search-results" class="dropdown-menu"></div>
</div>

<!-- Add this right before the closing </body> tag -->
<script>
// Debounce function
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Typeahead search implementation
$(document).ready(function() {
    const searchInput = $('#global-search');
    const resultsDropdown = $('#search-results');

    // Direct code navigation
    searchInput.on('input', function() {
        const query = $(this).val().trim();

        // Hide dropdown if query is too short
        if (query.length < 2) {
            resultsDropdown.hide();
            return;
        }

        // Check for entity code pattern
        const codePattern = /^(TKT|EMP|CLT|TSK|PLN)-\d{1,4}$/i;
        if (codePattern.test(query)) {
            // Direct code lookup
            $.ajax({
                url: '/shared/search/code-search.php',
                data: { code: query.toUpperCase() },
                success: function(response) {
                    if (response.found) {
                        window.location.href = response.deep_link_url;
                    }
                }
            });
            return;
        }

        // Regular search - debounced
        debouncedSearch(query);
    });

    // Hide dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.search-container').length) {
            resultsDropdown.hide();
        }
    });

    // Debounced search function
    const debouncedSearch = debounce(function(query) {
        $.ajax({
            url: '/shared/search/quick-search.php',
            data: { q: query },
            success: function(results) {
                resultsDropdown.empty();

                if (results.length === 0) {
                    resultsDropdown.append('<div class="p-2 text-muted">No results found</div>');
                } else {
                    results.forEach(function(result) {
                        const metadata = JSON.parse(result.metadata);
                        const code = metadata.code || '';
                        const title = getResultTitle(result);

                        resultsDropdown.append(`
                            <a href="${result.deep_link_url}" class="dropdown-item">
                                <span class="badge badge-secondary">${code}</span>
                                <strong>${title}</strong>
                                <div class="small text-muted">${getResultPreview(result)}</div>
                            </a>
                        `);
                    });

                    resultsDropdown.append(`
                        <div class="dropdown-divider"></div>
                        <a href="/admin/search.php?q=${encodeURIComponent(query)}" class="dropdown-item text-primary">
                            <i class="fas fa-search mr-2"></i>See all results
                        </a>
                    `);
                }

                resultsDropdown.show();
            }
        });
    }, 300);

    // Helper functions for result formatting
    function getResultTitle(result) {
        const metadata = JSON.parse(result.metadata);
        switch(result.item_type) {
            case 'client': return metadata.name || 'Client';
            case 'employee': return metadata.name || 'Employee';
            case 'ticket': return metadata.subject || 'Support Ticket';
            case 'task': return metadata.title || 'Task';
            case 'plan': return metadata.name || 'Plan';
            default: return 'Item';
        }
    }

    function getResultPreview(result) {
        // Create a preview based on the item type
        const metadata = JSON.parse(result.metadata);
        switch(result.item_type) {
            case 'client': 
                return `${metadata.company || ''} • ${metadata.status || ''}`;
            case 'ticket': 
                return `${metadata.status || ''} • ${metadata.priority || ''}`;
            case 'task': 
                return `${metadata.status || ''} • Due: ${metadata.deadline || 'N/A'}`;
            default:
                return result.item_type.charAt(0).toUpperCase() + result.item_type.slice(1);
        }
    }
});
</script>
