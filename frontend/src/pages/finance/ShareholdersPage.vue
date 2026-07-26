<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">
        <div class="col-12">
          <m-header icon="diamond" controlRoomButton="false" class="q-mt-xs">{{ $t('Shareholders') }}</m-header>
        </div>

        <!-- Company pool -->
        <div class="col-12 q-mt-sm">
          <div class="vip-pool">
            <div class="vip-pool__main">
              <div class="vip-pool__label"><q-icon name="savings" size="16px" class="q-mr-xs" />{{ $t('GeneralBudget') }} · {{ $t('Available') }}</div>
              <div class="vip-pool__val">{{ fmt(company.available_pool) }} <small>{{ company.base }}</small></div>
              <div class="vip-pool__hint">{{ $t('SharedEquallyHint') }}</div>
            </div>
            <div class="vip-pool__stats">
              <div class="vip-pool__stat"><span>{{ $t('DistributableProfit') }}</span><b>{{ fmt(company.earned_pool) }}</b></div>
              <div class="vip-pool__stat"><span>{{ $t('TotalDeposits') }}</span><b class="text-positive">{{ fmt(company.total_deposits) }}</b></div>
              <div class="vip-pool__stat"><span>{{ $t('TotalWithdrawals') }}</span><b class="text-negative">{{ fmt(company.total_withdrawals) }}</b></div>
            </div>
          </div>
        </div>

        <!-- Shareholder cards -->
        <div class="col-12 q-mt-md">
          <div class="row q-col-gutter-md">
            <div class="col-12 col-sm-6 col-md-3" v-for="s in shareholders" :key="s.id">
              <div class="vip-card" :class="{ 'vip-card--me': s.is_me, 'vip-card--sel': selected?.id === s.id }" @click="selectHolder(s)">
                <div class="vip-card__top">
                  <q-avatar size="46px" :style="`background:${holderColor(s.name)}1a;color:${holderColor(s.name)}`" class="text-weight-bold">{{ s.name.slice(0, 1) }}</q-avatar>
                  <div class="vip-card__id">
                    <div class="vip-card__name">{{ s.name }} <q-badge v-if="s.is_me" color="amber-7" class="q-ml-xs">{{ $t('You') }}</q-badge></div>
                    <div class="vip-card__share">{{ s.share_percent }}% {{ $t('Ownership') }}</div>
                  </div>
                </div>
                <div class="vip-card__avail">
                  <span>{{ $t('AvailableBalance') }}</span>
                  <b>{{ fmt(s.available) }} {{ s.base }}</b>
                </div>
                <div class="vip-card__rows">
                  <div><q-icon name="pie_chart" size="13px" class="text-primary" /> {{ $t('ProfitShare') }}<span>{{ fmt(s.profit_share) }}</span></div>
                  <div><q-icon name="south_west" size="13px" class="text-positive" /> {{ $t('Deposits') }}<span class="text-positive">{{ fmt(s.deposits) }}</span></div>
                  <div><q-icon name="north_east" size="13px" class="text-negative" /> {{ $t('Withdrawals') }}<span class="text-negative">{{ fmt(s.withdrawals) }}</span></div>
                  <div><q-icon name="home" size="13px" class="text-blue-grey-6" /> {{ $t('ExpenseShare') }}<span class="text-blue-grey-7">{{ fmt(s.expense_share) }}</span></div>
                </div>
                <div class="vip-card__actions" v-if="$can('partner-create')">
                  <q-btn dense unelevated no-caps color="positive" icon="add" :label="$t('Deposit')" size="sm" @click.stop="openTx(s, 'deposit')" />
                  <q-btn dense unelevated no-caps color="negative" icon="payments" :label="$t('Withdraw')" size="sm" @click.stop="openTx(s, 'withdraw')" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Selected shareholder history + shared activity -->
        <div class="col-12 col-lg-7 q-mt-md">
          <q-card flat bordered class="my_radio_less full-height">
            <q-card-section class="q-pb-xs row items-center">
              <div class="text-subtitle2 text-weight-bold"><q-icon name="history" size="18px" class="q-mr-xs" />{{ selected?.name || '—' }} · {{ $t('History') }}</div>
            </q-card-section>
            <q-separator />
            <q-markup-table flat dense class="my_radio_less" style="max-height:340px">
              <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('PaymentDate') }}</th><th class="text-left">{{ $t('Kind') }}</th><th class="text-right">{{ $t('Amount') }}</th><th class="text-left">{{ $t('Notes') }}</th></tr></thead>
              <tbody>
                <tr v-if="!myTx.length"><td colspan="4" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                <tr v-for="t in myTx" :key="t.id">
                  <td style="white-space:nowrap">{{ (t.tx_date || '').slice(0, 10) }}</td>
                  <td><q-chip dense size="sm" :color="t.type === 'deposit' ? 'green-1' : 'red-1'" :text-color="t.type === 'deposit' ? 'green-9' : 'red-9'">{{ $t(t.type === 'deposit' ? 'Deposit' : 'Withdrawal') }}</q-chip></td>
                  <td class="text-right text-weight-medium" :class="t.type === 'deposit' ? 'text-positive' : 'text-negative'">{{ t.type === 'deposit' ? '+' : '−' }}{{ fmt(t.amount_base) }}</td>
                  <td class="text-caption text-grey-7">{{ t.note || '—' }}</td>
                </tr>
              </tbody>
            </q-markup-table>
          </q-card>
        </div>

        <div class="col-12 col-lg-5 q-mt-md">
          <q-card flat bordered class="my_radio_less full-height">
            <q-card-section class="q-pb-xs row items-center">
              <div class="text-subtitle2 text-weight-bold"><q-icon name="groups" size="18px" class="q-mr-xs" />{{ $t('AllShareholdersActivity') }}</div>
            </q-card-section>
            <q-separator />
            <q-list separator style="max-height:340px;overflow:auto">
              <q-item v-for="a in activity" :key="a.id">
                <q-item-section avatar><q-icon :name="a.type === 'deposit' ? 'south_west' : 'north_east'" :color="a.type === 'deposit' ? 'positive' : 'negative'" /></q-item-section>
                <q-item-section>
                  <q-item-label><b>{{ a.partner?.name }}</b> · <span :class="a.type === 'deposit' ? 'text-positive' : 'text-negative'">{{ fmt(a.amount_base) }}</span></q-item-label>
                  <q-item-label caption>{{ (a.tx_date || '').slice(0, 10) }} · {{ $t(a.type === 'deposit' ? 'Deposit' : 'Withdrawal') }}<span v-if="a.note"> · {{ a.note }}</span></q-item-label>
                </q-item-section>
              </q-item>
              <q-item v-if="!activity.length"><q-item-section class="text-grey-5 text-center">{{ $t('NoRecordFound') }}</q-item-section></q-item>
            </q-list>
          </q-card>
        </div>
      </div>
    </m-backgrounds>

    <!-- Deposit / Withdraw modal -->
    <m-modal :showCM="txDialog" @update:showCM="txDialog = $event" card_style="width: 440px">
      <q-card class="bg-white" v-if="txHolder">
        <n-header :icon="txType === 'deposit' ? 'add_card' : 'payments'" :subtitle="txHolder.name">
          {{ $t(txType === 'deposit' ? 'Deposit' : 'Withdraw') }}
        </n-header>
        <q-separator />
        <q-form @submit="submitTx">
          <q-card-section>
            <div class="tx-info q-mb-sm">
              <div class="text-caption text-grey-7">{{ $t('AvailableBalance') }}</div>
              <div class="text-h6 text-weight-bold">{{ fmt(txHolder.available) }} {{ txHolder.base }}</div>
              <div class="text-caption text-grey-6" v-if="txType === 'withdraw'"><q-icon name="account_balance" size="13px" /> {{ $t('WithdrawFromBudget') }}</div>
            </div>
            <q-input outlined dense color="primary" type="number" step="any" v-model.number="txForm.amount" :label="$t('Amount')"
              :rules="[v => v > 0 || $t('FieldIsRequired'), v => txType === 'deposit' || v <= txHolder.available + 0.01 || $t('ExceedsAvailable')]" hide-bottom-space />
            <q-input outlined dense color="primary" class="q-mt-sm" type="textarea" autogrow v-model="txForm.note" :label="$t('Notes')" />
          </q-card-section>
          <q-separator />
          <n-submit :submitting="submitting" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'

const company = ref({ available_pool: 0, earned_pool: 0, total_deposits: 0, total_withdrawals: 0, base: 'AFN' })
const shareholders = ref([])
const activity = ref([])
const selected = ref(null)
const myTx = ref([])

const txDialog = ref(false)
const txHolder = ref(null)
const txType = ref('deposit')
const submitting = ref(false)
const txForm = reactive({ amount: null, note: '' })

const palette = ['#175A8C', '#0D9488', '#B45309', '#7C3AED']
function holderColor (name) {
  let h = 0; for (let i = 0; i < (name || '').length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0
  return palette[h % palette.length]
}
function fmt (v) { return Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 0 }) }

async function load () {
  try {
    const { data } = await api.get('/shareholders')
    company.value = data.company
    shareholders.value = data.shareholders
    const me = data.shareholders.find(s => s.is_me) || data.shareholders[0]
    if (me) selectHolder(me)
  } catch (_) {}
  try { const { data } = await api.get('/shareholders/activity'); activity.value = data } catch (_) {}
}
async function selectHolder (s) {
  selected.value = s
  try { const { data } = await api.get('/shareholders/' + s.id); myTx.value = data.transactions || [] } catch (_) { myTx.value = [] }
}
function openTx (s, type) { txHolder.value = s; txType.value = type; Object.assign(txForm, { amount: null, note: '' }); txDialog.value = true }
async function submitTx () {
  submitting.value = true
  try {
    const url = `/shareholders/${txHolder.value.id}/${txType.value === 'deposit' ? 'deposit' : 'withdraw'}`
    await api.post(url, { amount: txForm.amount, note: txForm.note })
    Notify.create({ type: 'positive', icon: 'cloud_done', message: 'Saved' })
    txDialog.value = false
    await load()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) } finally { submitting.value = false }
}

onMounted(load)
</script>

<style scoped>
.vip-pool { display: flex; flex-wrap: wrap; gap: 18px; align-items: center; justify-content: space-between; border-radius: 16px; padding: 18px 22px;
  background: linear-gradient(135deg, #123A66 0%, #175A8C 60%, #1E6BA8 100%); color: #fff; box-shadow: 0 12px 30px -14px rgba(18,58,102,.6); }
.vip-pool__label { font-size: 12px; opacity: .85; display: flex; align-items: center; }
.vip-pool__val { font-size: 30px; font-weight: 800; letter-spacing: -.5px; } .vip-pool__val small { font-size: 14px; opacity: .8; }
.vip-pool__hint { font-size: 11.5px; opacity: .75; margin-top: 2px; }
.vip-pool__stats { display: flex; gap: 22px; }
.vip-pool__stat { display: flex; flex-direction: column; } .vip-pool__stat span { font-size: 11px; opacity: .8; } .vip-pool__stat b { font-size: 16px; }
.vip-card { background: #fff; border: 1.5px solid #E7ECF3; border-radius: 16px; padding: 14px; cursor: pointer; transition: all .2s ease; height: 100%; }
.vip-card:hover { transform: translateY(-3px); box-shadow: 0 14px 28px -16px rgba(18,58,102,.4); }
.vip-card--sel { border-color: var(--q-primary); }
.vip-card--me { box-shadow: 0 0 0 2px #F59E0B33; border-color: #F59E0B; }
.vip-card__top { display: flex; align-items: center; gap: 10px; }
.vip-card__name { font-size: 15px; font-weight: 800; color: #1E293B; }
.vip-card__share { font-size: 11px; color: #94A3B8; }
.vip-card__avail { margin: 12px 0 8px; padding: 8px 10px; border-radius: 10px; background: color-mix(in srgb, var(--q-primary) 8%, #fff); display: flex; flex-direction: column; }
.vip-card__avail span { font-size: 10px; color: #64748B; } .vip-card__avail b { font-size: 19px; font-weight: 800; color: var(--q-primary); letter-spacing: -.3px; }
.vip-card__rows > div { display: flex; align-items: center; gap: 5px; font-size: 11.5px; color: #64748B; padding: 2px 0; }
.vip-card__rows > div span { margin-left: auto; font-weight: 700; }
.vip-card__actions { display: flex; gap: 6px; margin-top: 10px; } .vip-card__actions .q-btn { flex: 1; }
.tx-info { background: #F8FAFC; border: 1px solid #E7ECF3; border-radius: 10px; padding: 10px 12px; }
</style>
