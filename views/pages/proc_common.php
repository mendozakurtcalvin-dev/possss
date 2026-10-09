<?php
// Shared helpers for the procurement module pages.
// Loaded with require_once by each procurement page, so the guards below
// prevent "cannot redeclare" errors.

if (!function_exists('procPeso')) {
    function procPeso($amount) {
        return '&#8369;' . number_format(floatval($amount), 2);
    }
}

if (!function_exists('procStatusBadge')) {
    function procStatusBadge($status) {
        $map = [
            'draft' => ['badge-secondary', 'Draft'],
            'pending_approval' => ['badge-warning', 'Pending Approval'],
            'revision_requested' => ['badge-info', 'Revision Requested'],
            'approved' => ['badge-success', 'Approved'],
            'rejected' => ['badge-danger', 'Rejected'],
            'cancelled' => ['badge-secondary', 'Cancelled'],
        ];
        $entry = $map[$status] ?? ['badge-secondary', ucfirst(str_replace('_', ' ', (string) $status))];
        return '<span class="badge ' . $entry[0] . '">' . htmlspecialchars($entry[1]) . '</span>';
    }
}

if (!function_exists('procPoStatusBadge')) {
    function procPoStatusBadge($status) {
        $map = [
            'draft' => ['badge-secondary', 'Draft'],
            'pending' => ['badge-warning', 'Pending'],
            'finance_pending' => ['badge-warning', 'Finance Review'],
            'approved' => ['badge-success', 'Approved'],
            'ordered' => ['badge-primary', 'Ordered'],
            'sent' => ['badge-primary', 'Sent'],
            'acknowledged' => ['badge-info', 'Acknowledged'],
            'partially_received' => ['badge-warning', 'Partially Received'],
            'delivered' => ['badge-info', 'Delivered'],
            'received' => ['badge-success', 'Received'],
            'closed' => ['badge-success', 'Closed'],
            'cancelled' => ['badge-danger', 'Cancelled'],
        ];
        $entry = $map[$status] ?? ['badge-secondary', ucfirst(str_replace('_', ' ', (string) $status))];
        return '<span class="badge ' . $entry[0] . '">' . htmlspecialchars($entry[1]) . '</span>';
    }
}

if (!function_exists('procPaymentBadge')) {
    function procPaymentBadge($status) {
        $map = [
            'unpaid' => ['badge-danger', 'Unpaid'],
            'partial' => ['badge-warning', 'Partially Paid'],
            'paid' => ['badge-success', 'Paid'],
            'void' => ['badge-secondary', 'Void'],
        ];
        $entry = $map[$status] ?? ['badge-secondary', ucfirst((string) $status)];
        return '<span class="badge ' . $entry[0] . '">' . htmlspecialchars($entry[1]) . '</span>';
    }
}

if (!function_exists('procPagination')) {
    // Simple server-side pagination links. $baseUrl must already contain all
    // current filter query params except "page".
    function procPagination($totalRows, $page, $perPage, $baseUrl) {
        $pages = max(1, (int) ceil($totalRows / max(1, $perPage)));
        $page = min(max(1, (int) $page), $pages);
        if ($pages <= 1) {
            return '<div class="proc-pagination"><span class="proc-pagination-info">Showing ' . number_format($totalRows) . ' record(s)</span></div>';
        }
        $sep = strpos($baseUrl, '?') !== false ? '&' : '?';
        $html = '<div class="proc-pagination">';
        $html .= '<span class="proc-pagination-info">Showing ' . number_format($totalRows) . ' record(s) &middot; Page ' . $page . ' of ' . $pages . '</span>';
        $html .= '<span class="proc-pagination-links">';
        if ($page > 1) {
            $html .= '<a href="' . htmlspecialchars($baseUrl . $sep . 'page=' . ($page - 1)) . '">&laquo; Prev</a>';
        }
        $start = max(1, $page - 2);
        $end = min($pages, $page + 2);
        for ($i = $start; $i <= $end; $i++) {
            $html .= '<a class="' . ($i === $page ? 'active' : '') . '" href="' . htmlspecialchars($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a>';
        }
        if ($page < $pages) {
            $html .= '<a href="' . htmlspecialchars($baseUrl . $sep . 'page=' . ($page + 1)) . '">Next &raquo;</a>';
        }
        $html .= '</span></div>';
        return $html;
    }
}

if (!function_exists('procEmptyState')) {
    function procEmptyState($title, $text) {
        return '<div class="inv-empty-state">
            <div class="inv-empty-icon">&#128269;</div>
            <div class="inv-empty-title">' . htmlspecialchars($title) . '</div>
            <div class="inv-empty-text">' . htmlspecialchars($text) . '</div>
        </div>';
    }
}

if (!function_exists('procAccessDenied')) {
    function procAccessDenied() {
        return '<div class="inv-card" style="margin:2rem;text-align:center;padding:40px;">
            <div style="font-size:4rem;margin-bottom:1rem;">&#128683;</div>
            <h2 style="color:#111827;">Access Denied</h2>
            <p style="color:#6b7280;margin-top:8px;">You do not have permission to view this page.</p>
            <a href="?page=dashboard" class="inv-btn-primary" style="margin-top:1rem;text-decoration:none;">Go to Dashboard</a>
        </div>';
    }
}

if (!function_exists('procCsrfField')) {
    function procCsrfField() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
    }
}
