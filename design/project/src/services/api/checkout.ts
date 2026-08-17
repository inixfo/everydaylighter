import { apiRequest, minorToDisplay } from './client';

export type CheckoutQuote = {
  type: 'product' | 'bundle'; id: number; title: string; slug: string;
  subtotalMinor: number; discountMinor: number; totalMinor: number;
  subtotal: number; discount: number; total: number; currency: string;
  couponCode?: string; landingPageId?: number; landingPageVersionId?: number; offerKey?: string;
};

type CheckoutTarget = { product_id?: number; bundle_id?: number; landing_page_slug?: string; landing_page_id?: number; offer_key?: string };
export type CheckoutOrder = { id: number; order_number: string; total_minor: number; currency: string; guest_access_token?: string | null };
type QuoteResponse = { type:'product'|'bundle'; id:number; title:string; slug:string; subtotal_minor:number; discount_minor:number; total_minor:number; currency:string; coupon_code?:string; landing_page_id?:number; landing_page_version_id?:number; offer_key?:string };

export async function quoteCheckout(payload: CheckoutTarget & { coupon_code?: string }): Promise<CheckoutQuote> {
  const response = await apiRequest<{data: QuoteResponse}>('/checkout/quote', { method:'POST', body:JSON.stringify(payload) });
  const d=response.data;
  return { type:d.type,id:d.id,title:d.title,slug:d.slug,subtotalMinor:d.subtotal_minor,discountMinor:d.discount_minor,totalMinor:d.total_minor,subtotal:minorToDisplay(d.subtotal_minor)||0,discount:minorToDisplay(d.discount_minor)||0,total:minorToDisplay(d.total_minor)||0,currency:d.currency,couponCode:d.coupon_code,landingPageId:d.landing_page_id,landingPageVersionId:d.landing_page_version_id,offerKey:d.offer_key };
}

export async function createCheckoutOrder(payload: CheckoutTarget & { coupon_code?:string; customer_name:string; customer_email:string; customer_phone?:string; payment_method:string; tracking_context?:Record<string,unknown> }): Promise<CheckoutOrder> {
  const response=await apiRequest<{data:{order:CheckoutOrder;guest_access_token?:string|null}}>('/checkout/orders',{method:'POST',body:JSON.stringify(payload)});
  return {...response.data.order,guest_access_token:response.data.guest_access_token||null};
}

export async function initiateStripe(orderNumber:string):Promise<{checkout_url:string;checkout_session_id?:string}> {
  return (await apiRequest<{data:{checkout_url:string;checkout_session_id?:string}}>('/payments/stripe/initiate',{method:'POST',body:JSON.stringify({order_number:orderNumber})})).data;
}

export async function verifyStripeSession(sessionId:string):Promise<{order_number:string;payment_status:string}> {
  return (await apiRequest<{data:{order_number:string;payment_status:string}}>('/payments/stripe/verify',{method:'POST',body:JSON.stringify({session_id:sessionId})})).data;
}
