<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm" v-if="t">
        <!-- Hero -->
        <div class="col-12">
          <div class="sc-hero">
            <div class="sc-hero__bar">
              <div class="sc-hero__head">
                <div class="sc-hero__title">
                  <avatar-box type="tradesman" :id="t.id" :name="t.name" :size="56" :readonly="!$can('tradesman-edit')" />
                  <div>
                    <div class="sc-hero__name">{{ t.name }}</div>
                    <div class="sc-hero__meta">
                      <span v-if="t.code" class="sc-hero__code">{{ t.code }}</span>
                      <span class="sc-hero__pill" v-if="t.trade"><q-icon name="construction" size="13px" /> {{ t.trade }}</span>
                      <span class="sc-hero__pill" v-if="t.phone"><q-icon name="phone" size="13px" /> {{ t.phone }}</span>
                      <span class="sc-hero__pill" v-if="t.fingerprint_id"><q-icon name="fingerprint" size="13px" /> {{ t.fingerprint_id }}</span>
                      <span class="sc-hero__pill sc-hero__rating" v-if="t.summary?.rating_count"><q-icon name="star" size="13px" color="amber-4" /> {{ t.summary.rating_avg }} <span style="opacity:.7">({{ t.summary.rating_count }})</span></span>
                    </div>
                  </div>
                </div>
                <div class="q-gutter-xs row items-center">
                  <q-btn flat dense icon="print" color="white" :label="$t('PrintStatement')" @click="printStatement" />
                  <q-btn flat dense icon="edit" color="white" :label="$t('Edit')" v-if="$can('tradesman-edit')" @click="openEdit" />
                  <q-btn flat dense icon="arrow_back" color="white" @click="router.push('/subcontractors')" />
                </div>
              </div>
              <div class="sc-hero__progress">
                <div class="row items-center justify-between">
                  <div class="text-caption" style="opacity:.85">{{ $t('PaidVsContract') }}</div>
                  <div class="text-weight-bold">{{ paidPct }}%</div>
                </div>
                <q-linear-progress rounded size="12px" :value="paidPct / 100" color="amber-4" track-color="white" class="q-mt-xs" style="opacity:.95" />
              </div>
            </div>

            <div class="row q-col-gutter-sm sc-hero__stats">
              <div class="col-6 col-md-3" v-for="s in heroStats" :key="s.label">
                <div class="kpi-tile">
                  <q-icon :name="s.icon" size="20px" class="kpi-tile__icon" />
                  <div class="kpi-tile__val">{{ s.value }}</div>
                  <div class="kpi-tile__lbl">{{ $t(s.label) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pills -->
        <div class="col-12 q-mt-md">
          <div class="dash-nav">
            <button v-for="s in sections" :key="s.name" type="button" class="dash-pill" :class="{ 'dash-pill--active': tab === s.name }" @click="tab = s.name">
              <span class="dash-pill__orb"><q-icon :name="s.icon" size="14px" /></span>
              <span class="dash-pill__label">{{ $t(s.label) }}</span>
              <span v-if="s.count && s.count() > 0" class="dash-pill__count">{{ s.count() }}</span>
            </button>
          </div>
        </div>

        <div class="col-12 q-mt-sm">
          <q-card flat bordered class="my_radio_less dash-body">
            <div class="q-px-md q-pt-md">
              <tab-title :title="$t(activeSection.label)" :icon="activeSection.icon"
                :count="activeSection.count ? activeSection.count() : null" />
            </div>
            <q-tab-panels v-model="tab" animated>
              <!-- OVERVIEW -->
              <q-tab-panel name="overview">
                <div class="row q-col-gutter-md">
                  <div class="col-12 col-md-6">
                    <div class="text-subtitle2 q-mb-xs">{{ $t('Details') }}</div>
                    <q-markup-table flat bordered dense class="my_radio_less">
                      <tbody>
                        <tr><td class="text-grey-7">{{ $t('FatherName') }}</td><td class="text-weight-medium">{{ t.father_name || '—' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('IdNumber') }}</td><td>{{ t.cnic || '—' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('DefaultRate') }}</td><td>{{ fmt(t.default_rate) }} {{ base }} / {{ t.rate_unit || '—' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('StartDate') }}</td><td>{{ (t.start_date || '').slice(0, 10) || '—' }}</td></tr>
                        <tr><td class="text-grey-7">{{ $t('Fingerprint') }}</td><td>{{ t.fingerprint_id || '—' }}</td></tr>
                      </tbody>
                    </q-markup-table>
                  </div>
                  <div class="col-12 col-md-6">
                    <div class="text-subtitle2 q-mb-xs">{{ $t('RecentPayments') }}</div>
                    <q-markup-table flat bordered dense class="my_radio_less">
                      <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Date') }}</th><th class="text-left">{{ $t('Project') }}</th><th class="text-right">{{ $t('Amount') }}</th></tr></thead>
                      <tbody>
                        <tr v-if="!t.all_payments?.length"><td colspan="3" class="text-center text-grey-5">—</td></tr>
                        <tr v-for="p in (t.all_payments || []).slice(0, 6)" :key="p.id">
                          <td>{{ (p.payment_date || '').slice(0, 10) }}</td>
                          <td class="text-caption">{{ p.project_name }}</td>
                          <td class="text-right text-weight-medium">{{ fmt(p.amount) }} {{ p.currency }}</td>
                        </tr>
                      </tbody>
                    </q-markup-table>
                  </div>
                </div>
              </q-tab-panel>

              <!-- PROJECTS -->
              <q-tab-panel name="projects">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('ProjectsWorkedOn') }} ({{ (t.engagements || []).length }})</div>
                  <progress-btn color="teal" icon="add" v-if="$can('tradesman-edit')" @click="engDialog = true">{{ $t('AddEngagement') }}</progress-btn>
                </div>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Project') }}</th><th class="text-left">{{ $t('Trade') }}</th><th class="text-right">{{ $t('ContractAmount') }}</th><th class="text-right">{{ $t('Paid') }}</th><th class="text-right">{{ $t('Balance') }}</th></tr></thead>
                  <tbody>
                    <tr v-if="!(t.engagements || []).length"><td colspan="5" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                    <tr v-for="e in (t.engagements || [])" :key="e.id">
                      <td class="text-weight-medium">{{ e.project?.name }}<div class="text-caption text-grey-6">{{ e.project?.code }}</div></td>
                      <td>{{ e.trade || '—' }}</td>
                      <td class="text-right">{{ fmt(e.contract_amount) }} {{ e.currency }}</td>
                      <td class="text-right text-positive">{{ fmt(paidFor(e.id)) }}</td>
                      <td class="text-right text-weight-bold" :class="Number(e.contract_amount) - paidFor(e.id) > 0 ? 'text-negative' : 'text-grey-7'">{{ fmt(Number(e.contract_amount || 0) - paidFor(e.id)) }}</td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-tab-panel>

              <!-- PAYMENTS (weekly ledger across projects) -->
              <q-tab-panel name="payments">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('PaymentHistory') }} ({{ (t.all_payments || []).length }})</div>
                  <progress-btn color="teal" icon="add" v-if="$can('tradesman-edit')" @click="payDialog = true">{{ $t('AddPayment') }}</progress-btn>
                </div>
                <q-markup-table flat bordered dense class="my_radio_less" style="max-height:460px">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Date') }}</th><th class="text-left">{{ $t('Project') }}</th><th class="text-center">{{ $t('Kind') }}</th><th class="text-right">{{ $t('Amount') }}</th><th class="text-center">{{ $t('Confirmed') }}</th></tr></thead>
                  <tbody>
                    <tr v-if="!(t.all_payments || []).length"><td colspan="5" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                    <tr v-for="p in (t.all_payments || [])" :key="p.id">
                      <td style="white-space:nowrap">{{ (p.payment_date || '').slice(0, 10) }}</td>
                      <td class="text-caption">{{ p.project_name }}</td>
                      <td class="text-center"><q-chip dense size="sm" :color="p.kind === 'advance' ? 'orange-6' : 'green-7'" text-color="white">{{ $t(p.kind === 'advance' ? 'Advance' : 'Payment') }}</q-chip></td>
                      <td class="text-right text-weight-medium">{{ fmt(p.amount) }} {{ p.currency }}</td>
                      <td class="text-center">
                        <q-chip v-if="p.fingerprint_confirmed" dense size="sm" color="green-1" text-color="green-9"><q-icon name="fingerprint" size="14px" class="q-mr-xs" />{{ $t('Confirmed') }}<q-tooltip>{{ (p.fingerprint_confirmed_at || '').slice(0, 16).replace('T', ' ') }}</q-tooltip></q-chip>
                        <q-btn v-else-if="p.status !== 'pending' && $can('tradesman-edit')" size="sm" dense outline color="deep-purple-6" icon="fingerprint" :label="$t('Confirm')" @click="openVerify(p)" />
                        <span v-else class="text-grey-4">—</span>
                      </td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-tab-panel>

              <!-- MEASUREMENTS -->
              <q-tab-panel name="measurements">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('WorkMeasurements') }} ({{ (t.measurements || []).length }})</div>
                  <progress-btn color="teal" icon="add" v-if="$can('tradesman-edit')" @click="measDialog = true">{{ $t('AddMeasurement') }}</progress-btn>
                </div>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Date') }}</th><th class="text-left">{{ $t('Project') }}</th><th class="text-left">{{ $t('Description') }}</th><th class="text-right">{{ $t('Qty') }}</th><th class="text-left">{{ $t('Unit') }}</th><th class="text-right">{{ $t('UnitPrice') }}</th><th class="text-right">{{ $t('Amount') }}</th><th></th></tr></thead>
                  <tbody>
                    <tr v-if="!(t.measurements || []).length"><td colspan="8" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                    <tr v-for="m in (t.measurements || [])" :key="m.id">
                      <td>{{ (m.measure_date || '').slice(0, 10) }}</td>
                      <td class="text-caption">{{ m.project?.name }}</td>
                      <td>{{ m.description || '—' }}</td>
                      <td class="text-right">{{ fmt(m.quantity) }}</td>
                      <td>{{ m.unit }}</td>
                      <td class="text-right">{{ fmt(m.unit_price) }}</td>
                      <td class="text-right text-weight-bold">{{ fmt(m.amount) }}</td>
                      <td class="text-right"><q-btn v-if="$can('tradesman-edit')" size="sm" dense flat round icon="delete" color="negative" @click="removeMeasurement(m)" /></td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-tab-panel>

              <!-- RATINGS (immutable feedback, one per project) -->
              <q-tab-panel name="ratings">
                <div class="rate-note q-mb-md">
                  <q-icon name="info" size="16px" class="q-mr-xs" />
                  {{ $t('RatingsNote') }}
                </div>
                <div class="row items-center q-col-gutter-md q-mb-md">
                  <div class="col-auto">
                    <div class="rate-big">
                      <div class="rate-big__num">{{ t.summary?.rating_avg ?? '—' }}</div>
                      <div class="rate-big__stars">
                        <q-icon v-for="i in 5" :key="i" :name="i <= Math.round(t.summary?.rating_avg || 0) ? 'star' : 'star_border'" color="amber-7" size="18px" />
                      </div>
                      <div class="text-caption text-grey-6">{{ t.summary?.rating_count || 0 }} {{ $t('Ratings') }}</div>
                    </div>
                  </div>
                  <div class="col">
                    <div class="text-caption text-grey-7">{{ $t('RatingsFromProjects') }}</div>
                  </div>
                  <div class="col-auto">
                    <progress-btn color="teal" icon="add" v-if="$can('tradesman-edit')" @click="rateDialog = true">{{ $t('AddRating') }}</progress-btn>
                  </div>
                </div>
                <div v-if="!(t.ratings || []).length" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</div>
                <div v-else class="row q-col-gutter-md">
                  <div class="col-12 col-md-6" v-for="r in t.ratings" :key="r.id">
                    <q-card flat bordered class="my_radio_less rate-card">
                      <q-card-section class="q-py-sm">
                        <div class="row items-center justify-between">
                          <div class="text-weight-bold">{{ r.project?.name }}</div>
                          <div>
                            <q-icon v-for="i in 5" :key="i" :name="i <= r.stars ? 'star' : 'star_border'" color="amber-7" size="15px" />
                          </div>
                        </div>
                        <div class="row q-gutter-xs q-mt-xs">
                          <q-chip dense size="sm" color="blue-1" text-color="blue-9" v-if="r.quality">{{ $t('Quality') }}: {{ r.quality }}/5</q-chip>
                          <q-chip dense size="sm" color="teal-1" text-color="teal-9" v-if="r.timeliness">{{ $t('Timeliness') }}: {{ r.timeliness }}/5</q-chip>
                          <q-chip dense size="sm" color="orange-1" text-color="orange-9" v-if="r.safety">{{ $t('Safety') }}: {{ r.safety }}/5</q-chip>
                        </div>
                        <div v-if="r.comment" class="text-body2 q-mt-xs">“{{ r.comment }}”</div>
                        <div class="text-caption text-grey-6 q-mt-xs">— {{ r.rated_by_name || $t('Anonymous') }} · {{ (r.created_at || '').slice(0, 10) }}</div>
                      </q-card-section>
                    </q-card>
                  </div>
                </div>
              </q-tab-panel>

              <!-- FINGERPRINT payout -->
              <q-tab-panel name="fingerprint">
                <div class="fp-panel">
                  <div class="fp-panel__scan"><q-icon name="fingerprint" size="60px" /></div>
                  <div class="fp-panel__body">
                    <div class="text-h6 text-weight-bold">{{ t.name }}</div>
                    <div class="text-caption q-mb-xs" style="opacity:.85">{{ $t('FingerprintPayoutHint') }}</div>
                    <div class="fp-help q-mb-sm"><q-icon name="lightbulb" size="14px" class="q-mr-xs" />{{ $t('FingerprintHowTo') }} <b v-if="t.fingerprint_id">{{ t.fingerprint_id }}</b></div>
                    <div class="row q-col-gutter-sm">
                      <div class="col-6 col-sm-3"><stat-card dense icon="payments" :label="$t('TotalTaken')" :value="fmt(totalTaken)" :suffix="base" color="#16A34A" tint="#DCFCE7" /></div>
                      <div class="col-6 col-sm-3"><stat-card dense icon="account_balance_wallet" :label="$t('Balance')" :value="fmt(t.summary?.balance)" :suffix="base" color="#DC2626" tint="#FEE2E2" /></div>
                      <div class="col-6 col-sm-3"><stat-card dense icon="domain" :label="$t('Projects')" :value="t.summary?.projects" color="#175A8C" tint="#E0EDF7" /></div>
                      <div class="col-6 col-sm-3"><stat-card dense icon="event_repeat" :label="$t('Payments')" :value="(t.all_payments || []).length" color="#0D9488" tint="#CCFBF1" /></div>
                    </div>
                    <div class="row items-center q-gutter-sm q-mt-sm">
                      <q-btn outline color="primary" icon="print" :label="$t('PrintStatement')" @click="printStatement" />
                      <q-btn unelevated color="teal-7" icon="fingerprint" :label="$t('EnrollFingerprint')" :loading="enrolling" v-if="$can('fingerprint-create')" @click="enrollFinger" />
                      <q-chip v-if="enrolledCount" dense color="teal-1" text-color="teal-9"><q-icon name="verified" size="14px" class="q-mr-xs" />{{ enrolledCount }} {{ $t('Enrolled') }}</q-chip>
                    </div>
                  </div>
                </div>
                <q-markup-table flat bordered dense class="my_radio_less q-mt-md" style="max-height:340px">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Date') }}</th><th class="text-left">{{ $t('Project') }}</th><th class="text-center">{{ $t('Kind') }}</th><th class="text-right">{{ $t('Amount') }}</th></tr></thead>
                  <tbody>
                    <tr v-for="p in (t.all_payments || [])" :key="p.id">
                      <td style="white-space:nowrap">{{ (p.payment_date || '').slice(0, 10) }}</td>
                      <td class="text-caption">{{ p.project_name }}</td>
                      <td class="text-center"><q-chip dense size="sm" :color="p.kind === 'advance' ? 'orange-6' : 'green-7'" text-color="white">{{ $t(p.kind === 'advance' ? 'Advance' : 'Payment') }}</q-chip></td>
                      <td class="text-right text-weight-medium">{{ fmt(p.amount) }} {{ p.currency }}</td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-tab-panel>
            </q-tab-panels>
          </q-card>
        </div>
      </div>
    </m-backgrounds>

    <!-- Add engagement -->
    <m-modal :showCM="engDialog" @update:showCM="engDialog = $event" card_style="width: 460px">
      <q-card class="bg-white" v-if="t">
        <n-header icon="domain">{{ $t('AddEngagement') }}</n-header><q-separator />
        <q-form @submit="saveEngagement">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><q-select outlined dense color="primary" v-model="engForm.project_id" :options="projectOptions" emit-value map-options :label="$t('Project')" :rules="[v => !!v || $t('FieldIsRequired')]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="engForm.trade" @update:name="engForm.trade = $event" icon="construction" :label="$t('Trade')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><q-input outlined dense color="primary" type="number" step="any" v-model.number="engForm.contract_amount" :label="$t('ContractAmount')" /></div>
          </q-card-section>
          <q-separator /><n-submit :submitting="savingSub" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Add payment -->
    <m-modal :showCM="payDialog" @update:showCM="payDialog = $event" card_style="width: 460px">
      <q-card class="bg-white" v-if="t">
        <n-header icon="payments">{{ $t('AddPayment') }}</n-header><q-separator />
        <q-form @submit="savePayment">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><q-select outlined dense color="primary" v-model="payForm.project_id" :options="engagementOptions" emit-value map-options :label="$t('Project')" :rules="[v => !!v || $t('FieldIsRequired')]" /></div>
            <div class="col-6"><shamsi-date v-model="payForm.payment_date" color="primary" :label="$t('Date')" /></div>
            <div class="col-6"><q-select outlined dense color="primary" v-model="payForm.kind" :options="[{ label: $t('Payment'), value: 'payment' }, { label: $t('Advance'), value: 'advance' }]" emit-value map-options :label="$t('Kind')" /></div>
            <div class="col-12"><money-input v-model="payForm.amount" v-model:currency="payForm.currency" v-model:rate="payForm.rate" :allow-save-rate="false" :label="$t('Amount')" /></div>
            <div class="col-12"><q-input outlined dense color="primary" v-model="payForm.note" :label="$t('Notes')" /></div>
          </q-card-section>
          <q-separator /><n-submit :submitting="savingSub" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Add measurement -->
    <m-modal :showCM="measDialog" @update:showCM="measDialog = $event" card_style="width: 480px">
      <q-card class="bg-white" v-if="t">
        <n-header icon="straighten">{{ $t('AddMeasurement') }}</n-header><q-separator />
        <q-form @submit="saveMeasurement">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><q-select outlined dense color="primary" v-model="measForm.project_id" :options="engagementOptions" emit-value map-options :label="$t('Project')" :rules="[v => !!v || $t('FieldIsRequired')]" /></div>
            <div class="col-6"><shamsi-date v-model="measForm.measure_date" color="primary" :label="$t('Date')" /></div>
            <div class="col-6"><q-select outlined dense color="primary" v-model="measForm.unit" :options="['m2', 'm3', 'running-m', 'day', 'kg', 'lump']" :label="$t('Unit')" /></div>
            <div class="col-12"><n-name :name="measForm.description" @update:name="measForm.description = $event" icon="notes" :label="$t('Description')" :rules="[]" /></div>
            <div class="col-6"><q-input outlined dense color="primary" type="number" step="any" v-model.number="measForm.quantity" :label="$t('Qty')" /></div>
            <div class="col-6"><q-input outlined dense color="primary" type="number" step="any" v-model.number="measForm.unit_price" :label="$t('UnitPrice')" /></div>
            <div class="col-12"><div class="text-caption text-grey-7">{{ $t('Amount') }}: <b>{{ fmt((measForm.quantity || 0) * (measForm.unit_price || 0)) }} {{ base }}</b></div></div>
          </q-card-section>
          <q-separator /><n-submit :submitting="savingSub" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Virtual fingerprint scanner: confirm the subcontractor received a payment -->
    <q-dialog v-model="scanDialog" persistent>
      <q-card class="bg-white fp-scan-card" v-if="scanPay">
        <n-header icon="fingerprint" :subtitle="t?.name">{{ $t('ConfirmByFingerprint') }}</n-header>
        <q-separator />
        <q-card-section class="text-center">
          <div class="text-caption text-grey-7 q-mb-sm">{{ fmt(scanPay.amount) }} {{ scanPay.currency }} · {{ (scanPay.payment_date || '').slice(0, 10) }}</div>
          <div class="fp-reader" :class="scanState">
            <q-icon name="fingerprint" size="72px" />
            <div class="fp-reader__scanline" v-if="scanState === 'scanning'"></div>
          </div>
          <div class="fp-status" :class="scanState">
            <q-spinner v-if="scanState === 'scanning'" size="16px" class="q-mr-xs" />
            <q-icon v-else-if="scanState === 'ok'" name="check_circle" size="18px" class="q-mr-xs" />
            <q-icon v-else-if="scanState === 'fail'" name="error" size="18px" class="q-mr-xs" />
            {{ scanMsg }}
          </div>
          <!-- Virtual scanner controls (stand-in for the hardware device) -->
          <div class="fp-virtual q-mt-md">
            <div class="text-caption text-grey-6 q-mb-xs"><q-icon name="usb" size="13px" /> {{ $t('VirtualScannerNote') }}</div>
            <div class="row q-col-gutter-xs justify-center">
              <div class="col-auto"><q-btn unelevated color="deep-purple-6" icon="fingerprint" :label="$t('ScanCorrectThumb')" :loading="scanState === 'scanning'" @click="runScan(t.fingerprint_id)" /></div>
              <div class="col-auto"><q-btn outline color="grey-7" icon="do_not_touch" :label="$t('ScanWrongThumb')" :disable="scanState === 'scanning'" @click="runScan('FP-0000')" /></div>
            </div>
          </div>
        </q-card-section>
        <q-separator />
        <q-card-actions align="right" class="q-pa-sm"><q-btn flat :label="$t('Close')" color="grey-7" @click="scanDialog = false" /></q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Add rating (immutable) -->
    <m-modal :showCM="rateDialog" @update:showCM="rateDialog = $event" card_style="width: 480px">
      <q-card class="bg-white" v-if="t">
        <n-header icon="star" :subtitle="t.name">{{ $t('AddRating') }}</n-header><q-separator />
        <q-form @submit="saveRating">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><div class="rate-note"><q-icon name="lock" size="14px" class="q-mr-xs" />{{ $t('RatingImmutableNote') }}</div></div>
            <div class="col-12"><q-select outlined dense color="primary" v-model="rateForm.project_id" :options="engagementOptions" emit-value map-options :label="$t('Project')" :rules="[v => !!v || $t('FieldIsRequired')]" /></div>
            <div class="col-12">
              <div class="text-caption text-grey-7 q-mb-xs">{{ $t('OverallRating') }}</div>
              <q-rating v-model="rateForm.stars" size="30px" color="amber-7" icon="star_border" icon-selected="star" :max="5" />
            </div>
            <div class="col-4"><q-input outlined dense color="primary" type="number" min="1" max="5" v-model.number="rateForm.quality" :label="$t('Quality')" /></div>
            <div class="col-4"><q-input outlined dense color="primary" type="number" min="1" max="5" v-model.number="rateForm.timeliness" :label="$t('Timeliness')" /></div>
            <div class="col-4"><q-input outlined dense color="primary" type="number" min="1" max="5" v-model.number="rateForm.safety" :label="$t('Safety')" /></div>
            <div class="col-12"><n-name :name="rateForm.rated_by_name" @update:name="rateForm.rated_by_name = $event" icon="person" :label="$t('RatedBy')" :rules="[]" /></div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="rateForm.comment" :label="$t('Feedback')" /></div>
          </q-card-section>
          <q-separator /><n-submit :submitting="savingSub" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Edit -->
    <m-modal :showCM="editDialog" @update:showCM="editDialog = $event" card_style="width: 560px">
      <q-card class="bg-white" v-if="t">
        <n-header icon="edit">{{ $t('Edit') }} — {{ t.name }}</n-header><q-separator />
        <q-form @submit="saveEdit">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6"><n-name :name="editForm.name" @update:name="editForm.name = $event" icon="person" :label="$t('Name')" /></div>
            <div class="col-12 col-sm-6"><n-name :name="editForm.father_name" @update:name="editForm.father_name = $event" icon="family_restroom" :label="$t('FatherName')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="editForm.phone" @update:name="editForm.phone = $event" icon="phone" :label="$t('Phone')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="editForm.trade" @update:name="editForm.trade = $event" icon="construction" :label="$t('Trade')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="editForm.fingerprint_id" @update:name="editForm.fingerprint_id = $event" icon="fingerprint" :label="$t('FingerprintId')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><q-input outlined dense color="primary" type="number" step="any" v-model.number="editForm.default_rate" :label="$t('DefaultRate')" /></div>
          </q-card-section>
          <q-separator /><n-submit :submitting="savingSub" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Template-based payment verification (policy-driven, with fallbacks) -->
    <fp-verify v-model="verifyShow" :payment-id="verifyPayId" :person="t?.name" @verified="onVerified" />
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { useCurrency } from '@/composables/useCurrency'
import { useFingerprint } from '@/composables/useFingerprint'

const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance()
const { base, loadRates } = useCurrency()
const { loadSettings, loadDevices, defaultDevice, capture, enroll, enrollments } = useFingerprint()
const id = route.params.id

// ── Fingerprint (template-based) ──
const verifyShow = ref(false)
const verifyPayId = ref(null)
const enrolling = ref(false)
const enrolledCount = ref(0)
function openVerify (p) { verifyPayId.value = p.id; verifyShow.value = true }
async function onVerified () { await load() }
async function loadEnrollments () {
  try { enrolledCount.value = (await enrollments('tradesman', id)).length } catch (_) { enrolledCount.value = 0 }
}
async function enrollFinger () {
  enrolling.value = true
  try {
    await loadSettings(); await loadDevices()
    const d = defaultDevice()
    if (!d) { Notify.create({ type: 'warning', message: proxy.$t('NoActiveDevice') }); return }
    const cap = await capture(d.id)
    await enroll({ enrollable_type: 'tradesman', enrollable_id: Number(id), finger: 'right_thumb', template: cap.template, quality: cap.quality, device_id: cap.device_id })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'verified', message: proxy.$t('FingerprintEnrolled') })
    loadEnrollments()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Enroll failed' }) } finally { enrolling.value = false }
}

const t = ref(null)
const photo = ref(null)
const tab = ref('overview')
const projectOptions = ref([])

function fmt (v) { return Number(v || 0).toLocaleString('en-US') }

const sections = [
  { name: 'overview', label: 'Overview', icon: 'dashboard' },
  { name: 'projects', label: 'ProjectsWorkedOn', icon: 'domain', count: () => (t.value?.engagements || []).length },
  { name: 'payments', label: 'PaymentHistory', icon: 'payments', count: () => (t.value?.all_payments || []).length },
  { name: 'measurements', label: 'WorkMeasurements', icon: 'straighten', count: () => (t.value?.measurements || []).length },
  { name: 'ratings', label: 'Ratings', icon: 'star', count: () => (t.value?.ratings || []).length },
  { name: 'fingerprint', label: 'Fingerprint', icon: 'fingerprint' },
]
const activeSection = computed(() => sections.find(s => s.name === tab.value) || sections[0])

const paidPct = computed(() => {
  const c = Number(t.value?.summary?.contract_total || 0)
  const p = Number(t.value?.summary?.paid_total || 0) + Number(t.value?.summary?.advance_total || 0)
  return c > 0 ? Math.min(100, Math.round((p / c) * 100)) : 0
})
const totalTaken = computed(() => Number(t.value?.summary?.paid_total || 0) + Number(t.value?.summary?.advance_total || 0))
const heroStats = computed(() => [
  { label: 'Projects', value: t.value?.summary?.projects ?? 0, icon: 'domain' },
  { label: 'ContractTotal', value: fmt(t.value?.summary?.contract_total), icon: 'assignment' },
  { label: 'Paid', value: fmt(totalTaken.value), icon: 'payments' },
  { label: 'Balance', value: fmt(t.value?.summary?.balance), icon: 'account_balance_wallet' },
])
const engagementOptions = computed(() => (t.value?.engagements || []).map(e => ({ label: e.project?.name, value: e.project_id })))

function paidFor (engId) {
  return (t.value?.all_payments || []).filter(p => p.subcontractor_id === engId).reduce((s, p) => s + Number(p.amount || 0), 0)
}

async function load () {
  const { data } = await api.get('/tradesmen/' + id)
  t.value = data
  if (data.photo_mime?.startsWith('image/')) {
    try { const res = await api.get('/tradesmen/' + id + '/photo', { responseType: 'blob' }); photo.value = URL.createObjectURL(new Blob([res.data], { type: data.photo_mime })) } catch (_) {}
  }
}
async function loadProjects () { try { const { data } = await api.get('/projects'); projectOptions.value = (data || []).map(p => ({ label: p.name, value: p.id })) } catch (_) {} }

// dialogs
const engDialog = ref(false); const payDialog = ref(false); const measDialog = ref(false); const editDialog = ref(false)
const savingSub = ref(false)
const rateDialog = ref(false)
const rateForm = reactive({ project_id: null, stars: 5, quality: 5, timeliness: 4, safety: 5, rated_by_name: '', comment: '' })
const engForm = reactive({ project_id: null, trade: '', contract_amount: 0 })
const payForm = reactive({ project_id: null, payment_date: new Date().toISOString().slice(0, 10), kind: 'payment', amount: null, currency: 'AFN', rate: 1, note: '' })
const measForm = reactive({ project_id: null, measure_date: new Date().toISOString().slice(0, 10), unit: 'm2', description: '', quantity: null, unit_price: null })
const editForm = reactive({ name: '', father_name: '', phone: '', trade: '', fingerprint_id: '', default_rate: 0 })

async function post (url, body, dlg) {
  savingSub.value = true
  try {
    const { data } = await api.post(url, body); t.value = data
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    if (dlg) dlg.value = false
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) } finally { savingSub.value = false }
}
function saveEngagement () { post('/tradesmen/' + id + '/engagements', { ...engForm }, engDialog).then(() => Object.assign(engForm, { project_id: null, trade: '', contract_amount: 0 })) }
function savePayment () {
  post('/tradesmen/' + id + '/payments', { ...payForm }, payDialog).then(() => {
    payForm.amount = null; payForm.note = ''
    // Prompt the subcontractor to confirm receipt by fingerprint.
    const latest = (t.value?.all_payments || []).find(p => !p.fingerprint_confirmed)
    if (latest && t.value?.fingerprint_id) setTimeout(() => openScan(latest), 300)
  })
}
function saveMeasurement () { post('/tradesmen/' + id + '/measurements', { ...measForm }, measDialog).then(() => { measForm.quantity = null; measForm.unit_price = null; measForm.description = '' }) }
function saveRating () { post('/tradesmen/' + id + '/ratings', { ...rateForm }, rateDialog).then(() => Object.assign(rateForm, { project_id: null, stars: 5, quality: 5, timeliness: 4, safety: 5, rated_by_name: '', comment: '' })) }

// ── Virtual fingerprint confirmation of a payment ──
const scanDialog = ref(false)
const scanPay = ref(null)
const scanState = ref('idle')   // idle | scanning | ok | fail
const scanMsg = ref('')
function openScan (p) {
  if (!t.value?.fingerprint_id) { Notify.create({ type: 'warning', message: proxy?.$t ? proxy.$t('NoFingerprintRegistered') : 'No fingerprint registered' }); return }
  scanPay.value = p; scanState.value = 'idle'; scanMsg.value = proxy?.$t ? proxy.$t('PlaceThumb') : 'Place thumb on the scanner'; scanDialog.value = true
}
async function runScan (fp) {
  scanState.value = 'scanning'; scanMsg.value = proxy?.$t ? proxy.$t('Scanning') : 'Scanning…'
  // Simulate the hardware capture delay, then verify against the registered print.
  await new Promise(r => setTimeout(r, 1400))
  try {
    const { data } = await api.put('/subcontractor-payments/' + scanPay.value.id + '/confirm-fingerprint', { fingerprint_id: fp })
    t.value = data
    scanState.value = 'ok'; scanMsg.value = proxy?.$t ? proxy.$t('IdentityVerified') : 'Identity verified — payment confirmed'
    Notify.create({ type: 'positive', position: 'bottom', icon: 'fingerprint', message: scanMsg.value })
    setTimeout(() => { scanDialog.value = false }, 1100)
  } catch (e) {
    scanState.value = 'fail'; scanMsg.value = e?.response?.data?.message || (proxy?.$t ? proxy.$t('FingerprintNoMatch') : 'Fingerprint does not match')
  }
}
function removeMeasurement (m) { proxy.$delete('work-measurements/' + m.id, load) }

function openEdit () { Object.assign(editForm, { name: t.value.name, father_name: t.value.father_name || '', phone: t.value.phone || '', trade: t.value.trade || '', fingerprint_id: t.value.fingerprint_id || '', default_rate: Number(t.value.default_rate || 0) }); editDialog.value = true }
async function saveEdit () {
  savingSub.value = true
  try { const { data } = await api.put('/tradesmen/' + id, { ...editForm }); t.value = data; editDialog.value = false; Notify.create({ type: 'positive', position: 'bottom', message: 'Saved' }) }
  catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) } finally { savingSub.value = false }
}

function printStatement () {
  const p = t.value; if (!p) return
  const esc = (s) => String(s ?? '—').replace(/</g, '&lt;')
  const rowsHtml = (p.all_payments || []).map(x => `<tr><td>${(x.payment_date || '').slice(0, 10)}</td><td>${esc(x.project_name)}</td><td>${x.kind === 'advance' ? 'مساعده' : 'پرداخت'}</td><td style="text-align:end">${Number(x.amount).toLocaleString()} ${x.currency}</td></tr>`).join('')
  const html = `<!DOCTYPE html><html dir="rtl"><head><meta charset="utf-8"><title>${esc(p.name)}</title><style>
    body{font-family:Arial;margin:24px;color:#1E293B;font-size:12px}
    h1{color:#123A66;font-size:20px;margin:0}.sub{color:#64748B;margin-bottom:10px}
    table{border-collapse:collapse;width:100%;font-size:11.5px;margin-top:8px}
    th{background:#EEF4FB;text-align:start;padding:5px 7px;border:1px solid #CBD5E1}
    td{padding:5px 7px;border:1px solid #E2E8F0}.tot{font-size:15px;font-weight:bold;margin-top:8px}
  </style></head><body>
    <h1>${esc(p.name)} — راپور پرداخت</h1>
    <div class="sub">${esc(p.code)} · ${esc(p.trade)} · ${new Date().toLocaleDateString()}</div>
    <div class="tot">مجموع دریافت‌شده: ${Number(totalTaken.value).toLocaleString()} ${base.value} · باقی: ${Number(p.summary?.balance || 0).toLocaleString()} ${base.value}</div>
    <table><thead><tr><th>تاریخ</th><th>پروژه</th><th>نوعیت</th><th>مبلغ</th></tr></thead><tbody>${rowsHtml}</tbody></table>
    <script>window.onload=()=>window.print()<\/script></body></html>`
  const w = window.open('', '_blank'); if (!w) return
  w.document.write(html); w.document.close()
}

onMounted(() => { load(); loadProjects(); loadRates(); loadEnrollments() })
</script>

<style scoped>
.sc-hero__bar {
  background: linear-gradient(135deg, #123A66 0%, #175A8C 55%, #1E6BA8 100%);
  border-radius: 14px; padding: 16px 18px; color: #fff;
  box-shadow: 0 10px 26px -12px rgba(18, 58, 102, 0.6);
}
.sc-hero__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.sc-hero__title { display: flex; align-items: center; gap: 12px; }
.sc-hero__ava { background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.25); }
.sc-hero__name { font-size: 20px; font-weight: 800; letter-spacing: -0.3px; }
.sc-hero__meta { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.sc-hero__code { font-size: 12px; font-family: monospace; opacity: 0.85; }
.sc-hero__pill { display: inline-flex; align-items: center; gap: 3px; font-size: 11.5px; padding: 2px 8px; border-radius: 20px; background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.2); }
.sc-hero__progress { margin-top: 14px; }
.sc-hero__stats { margin-top: 10px; }
.kpi-tile { border: 1.5px solid #E2E8F0; border-radius: 12px; padding: 12px 14px; background: #fff; height: 100%; }
.kpi-tile__icon { color: var(--q-primary); opacity: 0.85; }
.kpi-tile__val { font-size: 18px; font-weight: 800; margin-top: 4px; color: #1E293B; }
.kpi-tile__lbl { font-size: 11px; color: #94A3B8; margin-top: 1px; }
.dash-nav { display: flex; align-items: center; gap: 4px; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); border: 1px solid #E2E8F0; border-radius: 999px; padding: 5px 8px; box-shadow: 0 10px 30px -14px rgba(18, 58, 102, 0.35); width: fit-content; max-width: 100%; margin: 0 auto; overflow-x: auto; position: sticky; top: 8px; z-index: 10; }
.dash-pill { display: flex; align-items: center; gap: 6px; border: none; background: transparent; cursor: pointer; padding: 5px 11px; border-radius: 999px; color: #64748B; font-size: 12px; font-weight: 700; transition: all 0.25s ease; white-space: nowrap; }
.dash-pill__orb { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #F1F5F9; }
.dash-pill__count { font-size: 10px; background: #E2E8F0; color: #475569; border-radius: 10px; padding: 1px 6px; font-weight: 800; }
.dash-pill--active { background: linear-gradient(135deg, #123A66, #1E6BA8); color: #fff; box-shadow: 0 6px 18px -6px rgba(18, 58, 102, 0.55); }
.dash-pill--active .dash-pill__orb { background: rgba(255, 255, 255, 0.18); color: #fff; }
.dash-pill--active .dash-pill__count { background: rgba(255, 255, 255, 0.2); color: #fff; }
.dash-body { border-radius: 14px; }
@media (max-width: 900px) { .dash-pill__label { display: none; } }
.fp-panel { display: flex; align-items: center; gap: 18px; background: linear-gradient(135deg, #0D3B66, #145DA0); color: #fff; border-radius: 14px; padding: 18px; }
.fp-panel__scan { opacity: 0.9; }
.fp-panel__body { flex: 1; }
.fp-help { display: inline-flex; align-items: center; font-size: 11.5px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25); border-radius: 8px; padding: 4px 9px; }
.sc-hero__rating { background: rgba(245, 197, 66, 0.22); border-color: rgba(245, 197, 66, 0.45); }
.rate-note { display: flex; align-items: center; font-size: 12.5px; color: #B45309; background: #FEF3C7; border: 1px dashed #F59E0B; border-radius: 8px; padding: 7px 10px; }
.rate-big { text-align: center; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 12px 20px; }
.rate-big__num { font-size: 34px; font-weight: 800; color: #123A66; line-height: 1; }
.rate-card { border-inline-start: 3px solid #F59E0B; }
/* Virtual fingerprint scanner */
.fp-scan-card { width: 420px; max-width: 95vw; }
.fp-reader {
  position: relative; width: 120px; height: 120px; margin: 6px auto 12px;
  display: flex; align-items: center; justify-content: center; border-radius: 18px;
  color: #94A3B8; background: #F1F5F9; border: 2px solid #E2E8F0; overflow: hidden;
  transition: all 0.3s ease;
}
.fp-reader.scanning { color: #6D28D9; border-color: #6D28D9; background: #F5F3FF; }
.fp-reader.ok { color: #16A34A; border-color: #16A34A; background: #F0FDF4; }
.fp-reader.fail { color: #DC2626; border-color: #DC2626; background: #FEF2F2; animation: fpshake 0.4s; }
.fp-reader__scanline {
  position: absolute; inset-inline: 6px; height: 3px; top: 10px;
  background: linear-gradient(90deg, transparent, #7C3AED, transparent);
  box-shadow: 0 0 8px #7C3AED; animation: fpscan 1.1s ease-in-out infinite;
}
@keyframes fpscan { 0% { top: 10px; } 50% { top: 104px; } 100% { top: 10px; } }
@keyframes fpshake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
.fp-status { display: inline-flex; align-items: center; font-size: 13px; font-weight: 600; color: #64748B; }
.fp-status.ok { color: #16A34A; }
.fp-status.fail { color: #DC2626; }
.fp-status.scanning { color: #6D28D9; }
.fp-virtual { border-top: 1px dashed #E2E8F0; padding-top: 10px; }
@media (prefers-color-scheme: dark) {
  .kpi-tile { background: #1E293B; border-color: #334155; }
  .kpi-tile__val { color: #F1F5F9; }
}
</style>
