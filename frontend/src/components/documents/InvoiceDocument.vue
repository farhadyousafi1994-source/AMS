<template>
  <!--
    Professional, print-ready invoice. Light theme, A4 portrait width, works
    on-screen and as a PDF (rasterized via printElementToPdf so Persian shapes
    correctly). Bilingual, RTL-aware.
  -->
  <div class="inv" :dir="rtl ? 'rtl' : 'ltr'">
    <!-- Accent bar -->
    <div class="inv__accent"></div>

    <!-- Header -->
    <div class="inv__head">
      <div class="inv__brand">
        <img v-if="company.logo" :src="company.logo" class="inv__logo" crossorigin="anonymous" alt="" />
        <div v-else class="inv__logo inv__logo--ph"><q-icon name="domain" size="30px" /></div>
        <div>
          <div class="inv__co">{{ companyName }}</div>
          <div v-if="companyAlt" class="inv__co-alt">{{ companyAlt }}</div>
          <div class="inv__co-meta">
            <span v-if="company.address">{{ company.address }}</span>
            <span v-if="company.city">{{ [company.city, company.country].filter(Boolean).join(', ') }}</span>
            <span v-if="company.phone"><q-icon name="call" size="11px" /> {{ company.phone }}</span>
            <span v-if="company.email"><q-icon name="mail" size="11px" /> {{ company.email }}</span>
            <span v-if="company.website"><q-icon name="language" size="11px" /> {{ company.website }}</span>
          </div>
        </div>
      </div>
      <div class="inv__title">
        <div class="inv__title-word">{{ $t('Invoice') }}</div>
        <div class="inv__no"># {{ invoice.invoice_no }}</div>
        <div class="inv__status" :style="`background:${statusTint};color:${statusColor}`">{{ $t(statusKey) }}</div>
      </div>
    </div>

    <!-- Parties + meta -->
    <div class="inv__parties">
      <div class="inv__party">
        <div class="inv__label">{{ $t('BillTo') }}</div>
        <div class="inv__party-name">{{ invoice.client_name || '—' }}</div>
        <div v-if="invoice.project" class="inv__party-sub">{{ $t('Project') }}: {{ invoice.project.name }}</div>
      </div>
      <div class="inv__meta">
        <div class="inv__meta-row"><span>{{ $t('InvoiceDate') }}</span><b>{{ fmtDay(invoice.invoice_date) }}</b></div>
        <div v-if="invoice.due_date" class="inv__meta-row"><span>{{ $t('DueDate') }}</span><b>{{ fmtDay(invoice.due_date) }}</b></div>
        <div class="inv__meta-row"><span>{{ $t('Currency') }}</span><b>{{ invoice.currency }}</b></div>
      </div>
    </div>

    <!-- Items -->
    <table class="inv__table">
      <thead>
        <tr>
          <th class="inv__c-idx">#</th>
          <th class="inv__c-desc">{{ $t('Description') }}</th>
          <th class="inv__c-num">{{ $t('Quantity') }}</th>
          <th class="inv__c-num">{{ $t('UnitPrice') }}</th>
          <th class="inv__c-num">{{ $t('Amount') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(it, i) in items" :key="it.id ?? i">
          <td class="inv__c-idx">{{ i + 1 }}</td>
          <td class="inv__c-desc">{{ it.description }}</td>
          <td class="inv__c-num">{{ fmt(it.qty) }}</td>
          <td class="inv__c-num">{{ fmt(it.unit_price) }}</td>
          <td class="inv__c-num">{{ fmt(it.amount) }} <span class="inv__cur">{{ invoice.currency }}</span></td>
        </tr>
        <tr v-if="!items.length"><td colspan="5" class="inv__empty">—</td></tr>
      </tbody>
    </table>

    <!-- Totals + payment -->
    <div class="inv__foot">
      <div class="inv__notes">
        <template v-if="invoice.notes">
          <div class="inv__label">{{ $t('Notes') }}</div>
          <div class="inv__notes-body">{{ invoice.notes }}</div>
        </template>
        <div class="inv__pay">
          <span class="inv__pay-dot" :style="`background:${statusColor}`"></span>
          {{ $t('PaymentStatus') }}: <b :style="`color:${statusColor}`">{{ $t(statusKey) }}</b>
        </div>
      </div>
      <div class="inv__totals">
        <div class="inv__t-row"><span>{{ $t('Subtotal') }}</span><b>{{ fmt(invoice.subtotal) }} {{ invoice.currency }}</b></div>
        <div v-if="Number(invoice.discount)" class="inv__t-row"><span>{{ $t('Discount') }}</span><b>− {{ fmt(invoice.discount) }}</b></div>
        <div v-if="Number(invoice.tax)" class="inv__t-row"><span>{{ $t('Tax') }}</span><b>{{ fmt(invoice.tax) }}</b></div>
        <div class="inv__t-row inv__t-row--grand"><span>{{ $t('Total') }}</span><b>{{ fmt(invoice.total) }} {{ invoice.currency }}</b></div>
        <div v-if="paid != null" class="inv__t-row inv__t-row--paid"><span>{{ $t('Paid') }}</span><b>{{ fmt(paid) }} {{ baseCur }}</b></div>
        <div v-if="balance != null" class="inv__t-row inv__t-row--bal"><span>{{ $t('Balance') }}</span><b>{{ fmt(balance) }} {{ baseCur }}</b></div>
      </div>
    </div>

    <!-- Signatures -->
    <div class="inv__sign">
      <div class="inv__sign-box"><div class="inv__sign-line"></div>{{ $t('AuthorizedSignature') }}</div>
      <div class="inv__sign-box"><div class="inv__sign-line"></div>{{ $t('ReceivedBy') }}</div>
    </div>

    <div class="inv__footer">{{ companyName }} · {{ $t('Invoice') }} {{ invoice.invoice_no }} · {{ fmtDay(invoice.invoice_date) }}</div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { i18n } from '@/boot/i18n'

const props = defineProps({
  invoice: { type: Object, required: true },
  company: { type: Object, default: () => ({}) },
  base: { type: String, default: 'AFN' },
})

const rtl = computed(() => i18n.locale === 'fa' || i18n.locale === 'pa')
const items = computed(() => props.invoice.items || [])
const baseCur = computed(() => props.base || 'AFN')
const paid = computed(() => props.invoice.paid_base ?? null)
const balance = computed(() => props.invoice.balance_base ?? null)

const companyName = computed(() => {
  const c = props.company || {}
  return (rtl.value && c.name_fa) ? c.name_fa : (c.name_en || c.name || 'Company')
})
const companyAlt = computed(() => {
  const c = props.company || {}
  return (rtl.value ? c.name_en : c.name_fa) || ''
})

const STATUS = {
  draft: ['Draft', '#64748B', '#F1F5F9'],
  sent: ['Sent', '#2563EB', '#DBEAFE'],
  partial: ['Partial', '#D97706', '#FEF3C7'],
  paid: ['Paid', '#16A34A', '#DCFCE7'],
  cancelled: ['Cancelled', '#DC2626', '#FEE2E2'],
}
const statusKey = computed(() => STATUS[props.invoice.status]?.[0] || 'Draft')
const statusColor = computed(() => STATUS[props.invoice.status]?.[1] || '#64748B')
const statusTint = computed(() => STATUS[props.invoice.status]?.[2] || '#F1F5F9')

function fmt (v) { return Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 2 }) }
function fmtDay (d) { return d ? String(d).slice(0, 10) : '—' }
</script>

<style scoped>
.inv {
  width: 780px; background: #fff; color: #1E293B; box-sizing: border-box;
  padding: 0 40px 36px; margin: 0 auto;
  font-family: 'afg_sans', 'Vazirmatn', 'Poppins', 'Segoe UI', Tahoma, Arial, sans-serif;
}
.inv__accent { height: 8px; background: linear-gradient(90deg, #123A66, #1E6BA8, #0EA5A4); margin: 0 -40px 24px; }

.inv__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
.inv__brand { display: flex; gap: 14px; align-items: flex-start; }
.inv__logo { width: 60px; height: 60px; object-fit: contain; border-radius: 12px; }
.inv__logo--ph { display: flex; align-items: center; justify-content: center; background: #EEF4FB; color: #123A66; }
.inv__co { font-size: 20px; font-weight: 800; color: #123A66; letter-spacing: .2px; }
.inv__co-alt { font-size: 13px; color: #475569; font-weight: 600; margin-top: 1px; }
.inv__co-meta { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 11px; color: #64748B; margin-top: 6px; max-width: 380px; }
.inv__title { text-align: right; }
.inv__title-word { font-size: 30px; font-weight: 800; color: #0F172A; letter-spacing: 2px; }
.inv__no { font-size: 13px; color: #475569; font-weight: 700; margin-top: 2px; }
.inv__status { display: inline-block; margin-top: 8px; padding: 4px 14px; border-radius: 999px; font-size: 12px; font-weight: 800; }

.inv__parties { display: flex; justify-content: space-between; gap: 24px; margin: 26px 0 18px; }
.inv__label { font-size: 10px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; color: #94A3B8; margin-bottom: 5px; }
.inv__party-name { font-size: 16px; font-weight: 800; color: #0F172A; }
.inv__party-sub { font-size: 12px; color: #64748B; margin-top: 2px; }
.inv__meta { min-width: 220px; background: #F8FAFC; border: 1px solid #E9EFF6; border-radius: 10px; padding: 10px 14px; }
.inv__meta-row { display: flex; justify-content: space-between; font-size: 12.5px; padding: 3px 0; color: #475569; }
.inv__meta-row b { color: #0F172A; }

.inv__table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 12.5px; }
.inv__table thead th { background: #123A66; color: #fff; padding: 10px 12px; font-weight: 700; text-align: right; }
.inv__table thead th.inv__c-idx { text-align: center; border-radius: 8px 0 0 0; width: 36px; }
.inv__table thead th.inv__c-desc { text-align: start; }
.inv__table thead th:last-child { border-radius: 0 8px 0 0; }
.inv__table tbody td { padding: 9px 12px; border-bottom: 1px solid #EDF2F7; }
.inv__c-idx { text-align: center; color: #94A3B8; }
.inv__c-desc { text-align: start; font-weight: 600; color: #1E293B; }
.inv__c-num { text-align: right; white-space: nowrap; }
.inv__cur { font-size: 10px; color: #94A3B8; }
.inv__empty { text-align: center; color: #CBD5E1; padding: 16px; }

.inv__foot { display: flex; justify-content: space-between; gap: 24px; margin-top: 20px; }
.inv__notes { flex: 1; }
.inv__notes-body { font-size: 12px; color: #475569; line-height: 1.5; max-width: 340px; }
.inv__pay { margin-top: 14px; font-size: 12.5px; color: #475569; display: flex; align-items: center; gap: 6px; }
.inv__pay-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
.inv__totals { min-width: 280px; }
.inv__t-row { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 2px; color: #475569; border-bottom: 1px dashed #E2E8F0; }
.inv__t-row b { color: #0F172A; }
.inv__t-row--grand { border-bottom: none; margin-top: 4px; background: #123A66; color: #fff; padding: 10px 14px; border-radius: 10px; font-size: 15px; }
.inv__t-row--grand span, .inv__t-row--grand b { color: #fff; font-weight: 800; }
.inv__t-row--paid b { color: #16A34A; }
.inv__t-row--bal b { color: #DC2626; }

.inv__sign { display: flex; justify-content: space-between; gap: 40px; margin-top: 48px; }
.inv__sign-box { flex: 1; text-align: center; font-size: 11.5px; color: #64748B; }
.inv__sign-line { border-top: 1.5px solid #CBD5E1; margin-bottom: 6px; }

.inv__footer { margin-top: 26px; padding-top: 12px; border-top: 1px solid #EDF2F7; text-align: center; font-size: 10.5px; color: #94A3B8; }
</style>
