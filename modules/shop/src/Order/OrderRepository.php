<?php

declare(strict_types=1);

namespace FlatCms\Modules\Shop\Order;

use FlatCms\Support\Fs;

/**
 * Orders are one JSON file per order under var/orders/<year>/<month>/.
 */
final class OrderRepository
{
    public function __construct(private readonly Fs $fs)
    {
    }

    public function create(Order $order): void
    {
        $this->fs->write($this->path($order->id, $order->createdAt), json_encode($order->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    }

    public function find(string $id): ?Order
    {
        $file = $this->path($id);
        if (!$this->fs->isFile($file)) {
            $file = $this->locate($id);
        }
        if ($file === null || !$this->fs->isFile($file)) {
            return null;
        }

        $data = json_decode($this->fs->read($file), true);

        return is_array($data) ? Order::fromArray($data) : null;
    }

    public function updateStatus(string $id, string $status): ?Order
    {
        $order = $this->find($id);
        if ($order === null) {
            return null;
        }

        $paidAt = $status === Order::STATUS_PAID ? date('c') : $order->paidAt;
        $updated = new Order(
            $order->id,
            $status,
            $order->total,
            $order->currency,
            $order->lines,
            $order->customer,
            $order->provider,
            $order->createdAt,
            $paidAt,
        );
        $this->create($updated);

        return $updated;
    }

    /**
     * @return list<Order>
     */
    public function all(int $limit = 100): array
    {
        $orders = [];
        foreach ($this->files() as $file) {
            $data = json_decode($this->fs->read($file), true);
            if (is_array($data)) {
                $orders[] = Order::fromArray($data);
            }
        }

        usort($orders, static fn (Order $a, Order $b): int => strcmp($b->createdAt, $a->createdAt));

        return array_slice($orders, 0, $limit);
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $base = $this->fs->path('var/orders');
        if (!is_dir($base)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'json') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function path(string $id, ?string $createdAt = null): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '', $id) ?? '';
        $timestamp = $createdAt !== null ? strtotime($createdAt) : false;
        $year = $timestamp !== false ? date('Y', $timestamp) : date('Y');
        $month = $timestamp !== false ? date('m', $timestamp) : date('m');

        return 'var/orders/' . $year . '/' . $month . '/' . $safe . '.json';
    }

    /**
     * Locate an order by id when the id no longer matches the folder layout.
     */
    private function locate(string $id): ?string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '', $id) ?? '';
        foreach ($this->files() as $file) {
            if (basename($file, '.json') === $safe) {
                return $file;
            }
        }

        return null;
    }
}
