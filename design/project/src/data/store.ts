import type { LucideIcon } from 'lucide-react';

export type ProductType = 'ebook' | 'guide' | 'template' | 'bundle' | 'toolkit';
export type ProductStatus = 'draft' | 'published';

export interface Product {
  id: string;
  slug: string;
  title: string;
  titleBn: string;
  category: string;
  type: ProductType;
  shortDescription: string;
  description: string;
  whatsIncluded: string[];
  regularPrice: number;
  salePrice?: number;
  rating: number;
  reviewCount: number;
  sales: number;
  revenue: number;
  cover: string;
  badges: string[];
  status: ProductStatus;
  updatedAt: string;
  tags: string[];
  fileSize: string;
  format: string;
  updatedAtLabel: string;
}

export interface Category {
  id: string;
  name: string;
  nameBn: string;
  icon: LucideIcon;
  count: number;
  color: 'brand' | 'danger' | 'success' | 'violet' | 'warning';
}

export interface Bundle {
  id: string;
  slug: string;
  title: string;
  titleBn: string;
  description: string;
  productIds: string[];
  regularTotal: number;
  bundlePrice: number;
  cover: string;
  savings: number;
  sales: number;
}

// Legacy export name retained so older admin/customer components keep working.
// EverydayLighter is USD-first.
export const formatBDT = (n: number) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: Number.isInteger(n) ? 0 : 2,
    maximumFractionDigits: 2,
  }).format(n);
