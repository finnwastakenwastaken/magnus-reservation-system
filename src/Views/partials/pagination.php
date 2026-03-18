<?php

declare(strict_types=1);

$page ??= 1;
$perPage ??= 10;
$total ??= 0;
$basePath ??= $_SERVER['REQUEST_URI'] ?? '';
$query = $_GET;
$lastPage = (int) max(1, ceil($total / $perPage));

if ($lastPage > 1):
?>
    <nav class="mt-4">
        <ul class="pagination mb-0">
            <?php
            $query['page'] = max(1, $page - 1);
            $prevUrl = strtok($basePath, '?') . '?' . http_build_query($query);
            $query['page'] = min($lastPage, $page + 1);
            $nextUrl = strtok($basePath, '?') . '?' . http_build_query($query);
            ?>
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($translator->get('common.previous'), ENT_QUOTES, 'UTF-8') ?></a></li>
            <li class="page-item disabled"><span class="page-link"><?= htmlspecialchars($translator->get('common.page') . ' ' . $page . '/' . $lastPage, ENT_QUOTES, 'UTF-8') ?></span></li>
            <li class="page-item <?= $page >= $lastPage ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($translator->get('common.next'), ENT_QUOTES, 'UTF-8') ?></a></li>
        </ul>
    </nav>
<?php endif; ?>
