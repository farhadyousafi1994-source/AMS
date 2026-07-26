<template>
  <q-page>
    <m-backgrounds>
      <div class="row my_radio_less q-pa-sm">

        <!-- Hero -->
        <div class="col-12">
          <div class="proj-hero">
            <div class="proj-hero__bar">
              <div class="proj-hero__head">
                <div class="proj-hero__title">
                  <div class="proj-hero__icon"><q-icon name="domain" size="26px" /></div>
                  <div>
                    <div class="proj-hero__name">{{ project.name || $t('Project') }}</div>
                    <div class="proj-hero__meta">
                      <span v-if="project.code" class="proj-hero__code">{{ project.code }}</span>
                      <q-chip dense size="sm" :color="statusColor(project.status)" text-color="white" class="q-ml-xs">{{ statusLabel(project.status) }}</q-chip>
                      <span class="proj-hero__pill"><q-icon name="category" size="13px" /> {{ typeLabel(project.type) }}</span>
                      <span class="proj-hero__pill" v-if="project.location"><q-icon name="place" size="13px" /> {{ project.location }}</span>
                    </div>
                  </div>
                </div>
                <div class="q-gutter-xs row items-center proj-hero__actions">
                  <div class="cur-toggle">
                    <button type="button" :class="{ active: displayCur === 'AFN' }" @click="displayCur = 'AFN'">AFN</button>
                    <button type="button" :class="{ active: displayCur === 'USD' }" @click="displayCur = 'USD'">USD</button>
                  </div>
                  <q-btn flat dense icon="print" color="white" :label="$t('PrintFullReport')" @click="printFullReport" />
                  <q-btn flat dense icon="edit" color="white" :label="$t('Edit')" v-if="$can('project-edit')" @click="router.push('/projects/edit/' + id)" />
                  <q-btn flat dense icon="arrow_back" color="white" @click="router.push('/projects')" />
                </div>
              </div>

              <!-- Progress track -->
              <div class="proj-hero__progress">
                <div class="row items-center justify-between">
                  <div class="text-caption" style="opacity:.85">{{ $t('Progress') }} · <q-icon name="auto_awesome" size="12px" /> {{ $t('Auto') }}</div>
                  <div class="text-weight-bold">{{ project.progress || 0 }}%</div>
                </div>
                <q-linear-progress rounded size="12px" :value="(project.progress || 0) / 100" color="amber-4" track-color="white" class="proj-hero__track q-mt-xs" />
              </div>
            </div>

            <!-- KPI tiles -->
            <div class="row q-col-gutter-sm proj-hero__stats">
              <div class="col-6 col-md-3" v-for="s in heroStats" :key="s.label">
                <div class="kpi-tile">
                  <q-icon :name="s.icon" size="20px" class="kpi-tile__icon" />
                  <div class="kpi-tile__val">{{ s.value }}</div>
                  <div class="kpi-tile__lbl">{{ $t(s.label) }}<span v-if="s.sub" class="text-negative"> · {{ s.sub }}</span></div>
                </div>
              </div>
            </div>

            <!-- Secondary facts + description -->
            <q-card flat bordered class="my_radio_less q-mt-sm">
              <q-card-section class="q-py-sm">
                <div class="row q-col-gutter-md">
                  <div class="col-6 col-sm-3" v-for="f in facts" :key="f.label">
                    <div class="text-caption text-grey-6">{{ $t(f.label) }}</div>
                    <div class="text-body2 text-weight-medium" :class="f.class">{{ f.value }}</div>
                  </div>
                </div>
                <div v-if="project.description" class="q-mt-sm text-body2 text-grey-8">{{ project.description }}</div>
              </q-card-section>
            </q-card>
          </div>
        </div>

        <!-- Floating section nav (small pills, wave on active) -->
        <div class="col-12 q-mt-md">
          <div class="dash-nav">
            <button v-for="s in sections" :key="s.name" type="button"
              class="dash-pill" :class="{ 'dash-pill--active': tab === s.name }" @click="tab = s.name">
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

              <!-- OVERVIEW — clean cards, map, live activity -->
              <q-tab-panel name="overview" class="q-pa-md">
                <!-- Gauge row (attachment-style arc meters + labour sparkline) -->
                <div class="row q-col-gutter-md q-mb-md">
                  <div class="col-6 col-md-3" v-for="g in gauges" :key="g.label">
                    <div class="gauge-card">
                      <div class="gauge-card__top">
                        <span class="gauge-card__delta" :style="`color:${g.color};background:${g.tint}`">
                          <q-icon :name="g.up ? 'arrow_drop_up' : 'remove'" size="16px" />{{ g.sub }}
                        </span>
                      </div>
                      <div class="gauge-card__val">{{ Math.round(g.pct) }}%</div>
                      <svg class="gauge-card__arc" viewBox="0 0 120 68">
                        <path d="M 15 62 A 45 45 0 0 1 105 62" fill="none" stroke="#EEF2F7" stroke-width="10" stroke-linecap="round" />
                        <path d="M 15 62 A 45 45 0 0 1 105 62" fill="none" :stroke="g.color" stroke-width="10" stroke-linecap="round"
                          :stroke-dasharray="(Math.min(100, Math.max(0, g.pct)) * 1.4137) + ' 999'" />
                      </svg>
                      <div class="gauge-card__lbl">{{ $t(g.label) }}</div>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="gauge-card">
                      <div class="gauge-card__top">
                        <span class="gauge-card__delta" style="color:#0284C7;background:#E0F2FE">
                          <q-icon name="groups" size="14px" class="q-mr-xs" />{{ labourSpark.latest }}
                        </span>
                      </div>
                      <div class="gauge-card__val">{{ labourSpark.latest }}</div>
                      <svg class="gauge-card__spark" viewBox="0 0 120 40" preserveAspectRatio="none">
                        <polyline :points="labourSpark.points" fill="none" stroke="#0284C7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                      </svg>
                      <div class="gauge-card__lbl">{{ $t('LabourCount') }}</div>
                    </div>
                  </div>
                </div>

                <div class="row q-col-gutter-md">
                  <!-- Section summary cards -->
                  <div class="col-12 col-lg-7">
                    <div class="row q-col-gutter-md">
                      <div class="col-12 col-sm-6" v-for="c in overviewCards" :key="c.section">
                        <div class="ov-card" @click="tab = c.section">
                          <div class="ov-card__head">
                            <span class="ov-card__icon" :style="`background:${c.tint}`"><q-icon :name="c.icon" size="17px" :style="`color:${c.color}`" /></span>
                            <span class="ov-card__title">{{ $t(c.label) }}</span>
                            <q-space />
                            <span class="ov-card__count">{{ c.total }}</span>
                          </div>
                          <div class="ov-card__body">
                            <div v-if="c.items.length === 0" class="text-caption text-grey-5">{{ $t('NoRecordFound') }}</div>
                            <div v-for="(it, ix) in c.items" :key="ix" class="ov-card__row">
                              <q-icon name="chevron_right" size="13px" class="text-grey-4" />
                              <span class="ov-card__row-main">{{ it.main }}</span>
                              <span class="ov-card__row-sub">{{ it.sub }}</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Site photos strip -->
                    <div class="ov-card ov-card--static q-mt-md" v-if="photoDocs.length">
                      <div class="ov-card__head">
                        <span class="ov-card__icon" style="background:#FFEDD5"><q-icon name="photo_camera" size="17px" style="color:#EA580C" /></span>
                        <span class="ov-card__title">{{ $t('SitePhotos') }}</span>
                        <q-space />
                        <a class="text-caption text-primary cursor-pointer" @click="tab = 'docs'">{{ $t('DrawingsDocs') }} →</a>
                      </div>
                      <div class="photo-strip">
                        <div class="photo-strip__item" v-for="d in photoDocs" :key="d.id" @click="openPreview(d)">
                          <img v-if="docThumbs[d.id]" :src="docThumbs[d.id]" :alt="d.title" />
                          <div v-else class="photo-strip__ph"><q-icon name="photo" size="22px" color="orange-4" /></div>
                          <div class="photo-strip__cap">{{ d.title }}</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Map + Live activity -->
                  <div class="col-12 col-lg-5">
                    <div class="ov-card ov-card--static q-mb-md">
                      <div class="ov-card__head">
                        <span class="ov-card__icon" style="background:#E0F2FE"><q-icon name="map" size="17px" style="color:#0284C7" /></span>
                        <span class="ov-card__title">{{ $t('ProjectLocation') }}</span>
                        <q-space />
                        <span class="text-caption text-grey-6">{{ project.location || '' }}</span>
                      </div>
                      <div class="ov-map">
                        <iframe v-if="project.location" :src="mapUrl" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        <div v-else class="ov-map__empty"><q-icon name="location_off" size="28px" class="q-mb-xs" /><div>{{ $t('NoLocationSet') }}</div></div>
                      </div>
                    </div>

                    <div class="ov-card ov-card--static">
                      <div class="ov-card__head">
                        <span class="ov-card__icon" style="background:#FEF3C7"><q-icon name="bolt" size="17px" style="color:#D97706" /></span>
                        <span class="ov-card__title">{{ $t('LiveActivity') }}</span>
                        <q-space />
                        <span class="live-dot"></span>
                      </div>
                      <div class="act-feed">
                        <div v-if="activity.length === 0" class="text-caption text-grey-5 q-pa-sm">{{ $t('NoRecordFound') }}</div>
                        <div v-for="a in activity" :key="a.id" class="act-item" :class="{ 'act-item--new': newActivityIds.has(a.id) }">
                          <span class="act-item__dot" :class="'act-item__dot--' + a.action"></span>
                          <div class="act-item__body">
                            <div class="act-item__text">{{ a.description }}</div>
                            <div class="act-item__meta">{{ a.user?.name || '—' }} · {{ a.created_at_human }}</div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </q-tab-panel>
              <!-- CAP TABLE (Investors & Capital) -->
              <q-tab-panel name="captable">
                <!-- Two live funding meters -->
                <div class="row q-col-gutter-md q-mb-md">
                  <div class="col-12 col-sm-6">
                    <div class="meter-card">
                      <div class="row items-center justify-between q-mb-xs">
                        <div class="text-caption text-weight-bold text-grey-7">
                          <q-icon name="savings" size="15px" class="q-mr-xs" />{{ $t('CapitalRaised') }}
                        </div>
                        <div class="text-caption" :class="capitalGap > 0 ? 'text-negative' : 'text-positive'">
                          {{ capitalGap > 0 ? $t('Gap') + ': ' + fmtMoney(capitalGap) : $t('FullyFunded') }}
                        </div>
                      </div>
                      <div class="text-h6 text-weight-bold text-primary">
                        {{ fmtMoney(funding.raised) }}
                        <span class="text-caption text-grey-6">/ {{ fmtMoney(funding.target) }} {{ project.currency }}</span>
                      </div>
                      <q-linear-progress rounded size="16px" :value="fundedRatio"
                        :color="capitalGap > 0 ? 'orange-8' : 'positive'" track-color="grey-3" class="q-mt-xs" />
                      <div class="text-caption text-grey-6 q-mt-xs">{{ Math.round(fundedRatio * 100) }}% {{ $t('OfTarget') }}</div>
                    </div>
                  </div>
                  <div class="col-12 col-sm-6">
                    <div class="meter-card">
                      <div class="row items-center justify-between q-mb-xs">
                        <div class="text-caption text-weight-bold text-grey-7">
                          <q-icon name="pie_chart" size="15px" class="q-mr-xs" />{{ $t('ProfitAllocated') }}
                        </div>
                        <div class="text-caption" :class="profitRemaining < 0 ? 'text-negative' : 'text-grey-7'">
                          {{ profitRemaining < 0 ? $t('OverAllocated') : $t('Unallocated') + ': ' + profitRemaining + '%' }}
                        </div>
                      </div>
                      <div class="text-h6 text-weight-bold" :class="profitRemaining < 0 ? 'text-negative' : 'text-primary'">
                        {{ Number(funding.profit_allocated || 0) }}%
                        <span class="text-caption text-grey-6">/ 100%</span>
                      </div>
                      <q-linear-progress rounded size="16px" :value="Math.min(1, (funding.profit_allocated || 0) / 100)"
                        :color="profitRemaining < 0 ? 'negative' : 'deep-purple-5'" track-color="grey-3" class="q-mt-xs" />
                      <div class="text-caption text-grey-6 q-mt-xs">{{ funding.participants || 0 }} {{ $t('Participants') }}</div>
                    </div>
                  </div>
                </div>

                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('CapTable') }}</div>
                  <progress-btn color="teal" icon="add" v-if="$can('investment-create')" @click="openInvestment()">
                    {{ $t('Add') }} {{ $t('Participant') }}
                  </progress-btn>
                </div>

                <div v-if="investmentsLoading" class="text-center q-py-md"><q-spinner color="primary" size="2em" /></div>
                <q-markup-table v-else flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft">
                    <tr>
                      <th class="text-left">{{ $t('Participant') }}</th>
                      <th class="text-right">{{ $t('Capital') }}</th>
                      <th class="text-center">{{ $t('ProfitPercent') }}</th>
                      <th class="text-left">{{ $t('Basis') }}</th>
                      <th class="text-right">{{ $t('ProfitReceived') }}</th>
                      <th class="text-right">{{ $t('Actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="investments.length === 0">
                      <td colspan="6" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td>
                    </tr>
                    <tr v-for="iv in investments" :key="iv.id">
                      <td class="text-weight-medium">
                        <q-icon :name="iv.is_company ? 'business' : 'person'" size="15px" class="q-mr-xs"
                          :color="iv.is_company ? 'secondary' : 'blue-grey-6'" />
                        {{ iv.participant_name }}
                        <q-chip v-if="iv.is_company" dense size="sm" color="amber-2" text-color="orange-9" class="q-ml-xs">{{ $t('Company') }}</q-chip>
                      </td>
                      <td class="text-right">{{ fmtMoney(iv.capital) }} {{ iv.currency }}</td>
                      <td class="text-center"><q-chip dense size="sm" color="deep-purple-1" text-color="deep-purple-9">{{ Number(iv.profit_percent) }}%</q-chip></td>
                      <td class="text-caption text-grey-7">{{ iv.basis || '—' }}</td>
                      <td class="text-right text-positive">{{ fmtMoney(iv.profit_received) }}</td>
                      <td class="text-right" style="white-space:nowrap">
                        <q-btn size="sm" dense flat round icon="edit" color="blue-8" v-if="$can('investment-edit')" @click="openInvestment(iv)" />
                        <q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('investment-delete')" @click="removeInvestment(iv)" />
                      </td>
                    </tr>
                  </tbody>
                </q-markup-table>
                <div class="text-caption text-grey-6 q-mt-xs">{{ $t('CapTableHint') }}</div>
              </q-tab-panel>

              <!-- RESOURCES (assets + materials) -->
              <q-tab-panel name="resources">
                <!-- Equipment / returnable assets -->
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2"><q-icon name="construction" size="18px" class="q-mr-xs" />{{ $t('EquipmentVehicles') }} ({{ resAssets.length }})</div>
                  <progress-btn color="teal" icon="add" v-if="$can('project-edit')" @click="resAssetOpen = !resAssetOpen">{{ $t('AddNew') }}</progress-btn>
                </div>
                <q-slide-transition>
                  <div v-show="resAssetOpen">
                    <q-form @submit="addResAsset" class="row q-col-gutter-sm items-end q-mb-md res-add">
                      <div class="col-12 col-sm-7">
                        <q-select outlined dense color="primary" v-model="resAssetForm.asset_id" :options="assetCatalog" emit-value map-options use-input @filter="filterAssets" :label="$t('SelectAsset')">
                          <template v-slot:option="scope">
                            <q-item v-bind="scope.itemProps"><q-item-section><q-item-label>{{ scope.opt.label }}</q-item-label><q-item-label caption>{{ $t('Available') }}: {{ scope.opt.available }} · {{ scope.opt.category }}</q-item-label></q-item-section></q-item>
                          </template>
                        </q-select>
                      </div>
                      <div class="col-6 col-sm-3"><q-input outlined dense color="primary" type="number" min="1" v-model.number="resAssetForm.quantity" :label="$t('Quantity')" /></div>
                      <div class="col-6 col-sm-2"><q-btn unelevated color="teal-7" icon="add" type="submit" :label="$t('Add')" class="full-width" /></div>
                    </q-form>
                  </div>
                </q-slide-transition>
                <q-markup-table flat bordered dense class="my_radio_less q-mb-lg">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Asset') }}</th><th class="text-left">{{ $t('Category') }}</th><th class="text-right">{{ $t('Quantity') }}</th><th class="text-right">{{ $t('Actions') }}</th></tr></thead>
                  <tbody>
                    <tr v-if="resAssets.length === 0"><td colspan="4" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                    <tr v-for="a in resAssets" :key="a.id">
                      <td class="text-weight-medium"><q-icon :name="assetIcon(a.asset?.category)" size="16px" class="q-mr-xs" color="blue-grey-6" />{{ a.asset?.name }}</td>
                      <td>{{ a.asset?.category }}</td>
                      <td class="text-right">{{ a.quantity }}</td>
                      <td class="text-right"><q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('project-edit')" @click="removeResAsset(a)" /></td>
                    </tr>
                  </tbody>
                </q-markup-table>

                <!-- Consumable materials -->
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2"><q-icon name="grain" size="18px" class="q-mr-xs" />{{ $t('Materials') }} ({{ resMaterials.length }})</div>
                  <progress-btn color="teal" icon="add" v-if="$can('project-edit')" @click="resMatOpen = !resMatOpen">{{ $t('AddNew') }}</progress-btn>
                </div>
                <q-slide-transition>
                  <div v-show="resMatOpen">
                    <q-form @submit="addResMaterial" class="row q-col-gutter-sm items-end q-mb-md res-add">
                      <div class="col-12 col-sm-6"><n-name :name="resMatForm.name" @update:name="resMatForm.name = $event" icon="grain" :label="$t('Material')" :placeholder="$t('MaterialEg')" /></div>
                      <div class="col-5 col-sm-2"><q-input outlined dense color="primary" type="number" v-model.number="resMatForm.quantity" :label="$t('Quantity')" /></div>
                      <div class="col-4 col-sm-2"><q-select outlined dense color="primary" v-model="resMatForm.unit" :options="unitOptions" use-input new-value-mode="add-unique" :label="$t('Unit')" /></div>
                      <div class="col-3 col-sm-2"><q-btn unelevated color="teal-7" icon="add" type="submit" :label="$t('Add')" class="full-width" /></div>
                    </q-form>
                  </div>
                </q-slide-transition>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft"><tr><th class="text-left">{{ $t('Material') }}</th><th class="text-right">{{ $t('Quantity') }}</th><th class="text-left">{{ $t('Unit') }}</th><th class="text-right">{{ $t('Actions') }}</th></tr></thead>
                  <tbody>
                    <tr v-if="resMaterials.length === 0"><td colspan="4" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
                    <tr v-for="m in resMaterials" :key="m.id">
                      <td class="text-weight-medium">{{ m.name }}</td>
                      <td class="text-right">{{ fmtMoney(m.quantity) }}</td>
                      <td>{{ m.unit || '—' }}</td>
                      <td class="text-right"><q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('project-edit')" @click="removeResMaterial(m)" /></td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-tab-panel>

              <!-- TASKS (Work Breakdown) -->
              <q-tab-panel name="tasks">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('WorkBreakdown') }} ({{ tasks.length }})</div>
                  <progress-btn color="teal" icon="add" v-if="$can('task-create')" @click="openTask()">
                    {{ $t('Add') }} {{ $t('Task') }}
                  </progress-btn>
                </div>

                <div v-if="tasksLoading" class="text-center q-py-md"><q-spinner color="primary" size="2em" /></div>
                <div v-else-if="tasks.length === 0" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</div>

                <div v-for="group in tasksByPhase" :key="group.phase" class="q-mb-sm">
                  <div class="phase-head">
                    <q-icon name="layers" size="16px" class="q-mr-xs" />{{ group.phase }}
                    <q-space />
                    <span class="text-caption text-grey-6">{{ group.done }}/{{ group.items.length }} {{ $t('Done') }}</span>
                  </div>
                  <q-markup-table flat bordered dense class="my_radio_less">
                    <thead class="bg-theme-soft">
                      <tr>
                        <th class="text-left">{{ $t('Task') }}</th>
                        <th class="text-left">{{ $t('Assignee') }}</th>
                        <th class="text-center">{{ $t('Priority') }}</th>
                        <th class="text-center">{{ $t('Status') }}</th>
                        <th class="text-left" style="min-width:120px">{{ $t('Progress') }}</th>
                        <th class="text-left">{{ $t('DueDate') }}</th>
                        <th class="text-right">{{ $t('Actions') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="t in group.items" :key="t.id">
                        <td class="text-weight-medium">{{ t.title }}</td>
                        <td>
                          <q-chip v-if="t.assignee" dense size="sm" color="blue-grey-2" text-color="blue-grey-9">
                            <q-icon name="group" size="13px" class="q-mr-xs" />{{ t.assignee }}
                          </q-chip>
                          <span v-else class="text-grey-5">—</span>
                        </td>
                        <td class="text-center">
                          <q-chip dense size="sm" :color="priorityColor(t.priority)" text-color="white">{{ $t(priorityKey(t.priority)) }}</q-chip>
                        </td>
                        <td class="text-center">
                          <q-chip dense size="sm" :color="taskStatusColor(t.status)" text-color="white">{{ $t(taskStatusKey(t.status)) }}</q-chip>
                        </td>
                        <td>
                          <q-linear-progress rounded size="12px" :value="(t.progress || 0) / 100" :color="taskStatusColor(t.status)" track-color="grey-3" />
                          <span class="text-caption text-grey-6">{{ t.progress || 0 }}%</span>
                        </td>
                        <td style="white-space:nowrap">{{ t.due_date ? t.due_date.slice(0,10) : '—' }}</td>
                        <td class="text-right" style="white-space:nowrap">
                          <q-btn size="sm" dense flat round icon="edit" color="blue-8" v-if="$can('task-edit')" @click="openTask(t)" />
                          <q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('task-delete')" @click="removeTask(t)" />
                        </td>
                      </tr>
                    </tbody>
                  </q-markup-table>
                </div>
              </q-tab-panel>

              <!-- SITES -->
              <q-tab-panel name="sites">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('Sites') }} ({{ project.sites?.length || 0 }})</div>
                  <progress-btn color="teal" icon="add" v-if="$can('site-create')" @click="openSite()">
                    {{ $t('Add') }} {{ $t('Site') }}
                  </progress-btn>
                </div>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft">
                    <tr>
                      <th class="text-left">#</th>
                      <th class="text-left">{{ $t('Name') }}</th>
                      <th class="text-left">{{ $t('Location') }}</th>
                      <th class="text-left">{{ $t('InCharge') }}</th>
                      <th class="text-center">{{ $t('Status') }}</th>
                      <th class="text-right">{{ $t('Actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!project.sites || project.sites.length === 0">
                      <td colspan="6" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td>
                    </tr>
                    <tr v-for="(s, i) in project.sites" :key="s.id">
                      <td class="text-grey-6">{{ i + 1 }}</td>
                      <td class="text-weight-medium">{{ s.name }}</td>
                      <td>{{ s.location || '—' }}</td>
                      <td>{{ s.in_charge || '—' }}</td>
                      <td class="text-center">
                        <q-chip dense size="sm" :color="s.active ? 'positive' : 'grey'" text-color="white">
                          {{ s.active ? $t('Active') : $t('Inactive') }}
                        </q-chip>
                      </td>
                      <td class="text-right">
                        <q-btn size="sm" dense flat round icon="edit" color="blue-8" v-if="$can('site-edit')" @click="openSite(s)" />
                        <q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('site-delete')" @click="removeSite(s)" />
                      </td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-tab-panel>

              <!-- MILESTONES -->
              <q-tab-panel name="milestones">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('Milestones') }} ({{ project.milestones?.length || 0 }})</div>
                  <progress-btn color="teal" icon="add" v-if="$can('milestone-create')" @click="openMilestone()">
                    {{ $t('Add') }} {{ $t('Programme') }}
                  </progress-btn>
                </div>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft">
                    <tr>
                      <th class="text-left">#</th>
                      <th class="text-left">{{ $t('Title') }}</th>
                      <th class="text-left">{{ $t('DueDate') }}</th>
                      <th class="text-center">{{ $t('Status') }}</th>
                      <th class="text-left" style="min-width:140px">{{ $t('Progress') }}</th>
                      <th class="text-right">{{ $t('Actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!project.milestones || project.milestones.length === 0">
                      <td colspan="6" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td>
                    </tr>
                    <tr v-for="(m, i) in project.milestones" :key="m.id">
                      <td class="text-grey-6">{{ i + 1 }}</td>
                      <td class="text-weight-medium">{{ m.title }}</td>
                      <td>{{ m.due_date ? m.due_date.slice(0,10) : '—' }}</td>
                      <td class="text-center">
                        <q-chip dense size="sm" :color="mStatusColor(m.status)" text-color="white">
                          {{ $t(mStatusKey(m.status)) }}
                        </q-chip>
                      </td>
                      <td>
                        <q-linear-progress rounded size="12px" :value="(m.progress || 0) / 100" color="primary" track-color="grey-3" />
                        <span class="text-caption text-grey-6">{{ m.progress || 0 }}%</span>
                      </td>
                      <td class="text-right">
                        <q-btn size="sm" dense flat round icon="edit" color="blue-8" v-if="$can('milestone-edit')" @click="openMilestone(m)" />
                        <q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('milestone-delete')" @click="removeMilestone(m)" />
                      </td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-tab-panel>

              <!-- DAILY LOGS -->
              <q-tab-panel name="logs">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('DailyLogs') }} ({{ logs.length }})</div>
                  <progress-btn color="teal" icon="add" v-if="$can('daily-log-create')" @click="openLog()">
                    {{ $t('Add') }} {{ $t('Log') }}
                  </progress-btn>
                </div>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft">
                    <tr>
                      <th class="text-left">{{ $t('LogDate') }}</th>
                      <th class="text-left">{{ $t('Site') }}</th>
                      <th class="text-center">{{ $t('LabourCount') }}</th>
                      <th class="text-left">{{ $t('Weather') }}</th>
                      <th class="text-left">{{ $t('WorkDone') }}</th>
                      <th class="text-left">{{ $t('CreatedBy') }}</th>
                      <th class="text-right">{{ $t('Actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="logsLoading">
                      <td colspan="7" class="text-center q-py-md"><q-spinner color="primary" size="2em" /></td>
                    </tr>
                    <tr v-else-if="logs.length === 0">
                      <td colspan="7" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td>
                    </tr>
                    <tr v-for="l in logs" :key="l.id">
                      <td class="text-weight-medium" style="white-space:nowrap">{{ l.log_date ? l.log_date.slice(0,10) : '—' }}</td>
                      <td>{{ l.site?.name || '—' }}</td>
                      <td class="text-center">
                        <q-chip dense size="sm" color="blue-grey-2" text-color="blue-grey-9">
                          <q-icon name="groups" size="14px" class="q-mr-xs" />{{ l.labour_count || 0 }}
                        </q-chip>
                      </td>
                      <td>{{ l.weather || '—' }}</td>
                      <td class="text-caption" style="max-width:280px">{{ l.work_done || '—' }}</td>
                      <td class="text-caption text-blue-grey-7">{{ l.user?.name || '—' }}</td>
                      <td class="text-right" style="white-space:nowrap">
                        <q-btn size="sm" dense flat round icon="edit" color="blue-8" v-if="$can('daily-log-edit')" @click="openLog(l)" />
                        <q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('daily-log-delete')" @click="removeLog(l)" />
                      </td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-tab-panel>

              <!-- SUBCONTRACTORS -->
              <q-tab-panel name="subs">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('Subcontractors') }} ({{ subs.length }})</div>
                  <progress-btn color="teal" icon="add" v-if="$can('subcontractor-create')" @click="openSub()">
                    {{ $t('Add') }} {{ $t('Subcontractor') }}
                  </progress-btn>
                </div>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft">
                    <tr>
                      <th class="text-left">{{ $t('Name') }}</th>
                      <th class="text-left">{{ $t('Trade') }}</th>
                      <th class="text-right">{{ $t('ContractAmount') }}</th>
                      <th class="text-right">{{ $t('Paid') }}</th>
                      <th class="text-right">{{ $t('Advance') }}</th>
                      <th class="text-right">{{ $t('Balance') }}</th>
                      <th class="text-right">{{ $t('Actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="subsLoading">
                      <td colspan="7" class="text-center q-py-md"><q-spinner color="primary" size="2em" /></td>
                    </tr>
                    <tr v-else-if="subs.length === 0">
                      <td colspan="7" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td>
                    </tr>
                    <tr v-for="s in subs" :key="s.id">
                      <td class="text-weight-medium">
                        {{ s.name }}
                        <div v-if="s.phone" class="text-caption text-grey-6">{{ s.phone }}</div>
                      </td>
                      <td>{{ s.trade || '—' }}</td>
                      <td class="text-right">{{ fmtMoney(s.contract_amount) }} {{ s.currency }}</td>
                      <td class="text-right text-positive">{{ fmtMoney(s.paid_total) }}</td>
                      <td class="text-right text-orange-8">{{ fmtMoney(s.advance_total) }}</td>
                      <td class="text-right text-weight-bold" :class="Number(s.balance) > 0 ? 'text-negative' : 'text-grey-7'">
                        {{ fmtMoney(s.balance) }}
                      </td>
                      <td class="text-right" style="white-space:nowrap">
                        <q-btn size="sm" dense flat round icon="payments" color="teal-8" v-if="$can('sub-payment-list') || $can('sub-payment-create')" @click="openPayments(s)">
                          <q-tooltip>{{ $t('Payments') }}</q-tooltip>
                        </q-btn>
                        <q-btn size="sm" dense flat round icon="edit" color="blue-8" v-if="$can('subcontractor-edit')" @click="openSub(s)" />
                        <q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('subcontractor-delete')" @click="removeSub(s)" />
                      </td>
                    </tr>
                  </tbody>
                </q-markup-table>
                <div class="text-caption text-grey-6 q-mt-xs">
                  {{ $t('SettlementHint') }}
                </div>
              </q-tab-panel>

              <!-- PARTY ACCOUNTS: who paid / who received inside this project -->
              <q-tab-panel name="accounts">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('PartyAccounts') }} ({{ pAccounts.length }})</div>
                  <q-btn dense outline color="primary" icon="open_in_new" :label="$t('PartyAccounts')" to="/accounts" v-if="$can('party-list')" />
                </div>
                <div class="row q-col-gutter-xs q-mb-sm">
                  <div class="col-6 col-sm-4">
                    <stat-card dense icon="south_west" :label="$t('TotalMoneyIn')"
                      :value="pAccCards.tin.value" :suffix="pAccCards.tin.suffix" :sub="pAccCards.tin.sub"
                      color="#16A34A" tint="#DCFCE7" />
                  </div>
                  <div class="col-6 col-sm-4">
                    <stat-card dense icon="north_east" :label="$t('TotalMoneyOut')"
                      :value="pAccCards.tout.value" :suffix="pAccCards.tout.suffix" :sub="pAccCards.tout.sub"
                      color="#DC2626" tint="#FEE2E2" />
                  </div>
                  <div class="col-12 col-sm-4">
                    <stat-card dense icon="account_balance_wallet" :label="$t('TotalBalance')"
                      :value="pAccCards.bal.value" :suffix="pAccCards.bal.suffix" :sub="pAccCards.bal.sub"
                      :color="pAccCards.netBase > 0 ? '#DC2626' : (pAccCards.netBase < 0 ? '#16A34A' : '#94A3B8')"
                      :tint="pAccCards.netBase > 0 ? '#FEE2E2' : (pAccCards.netBase < 0 ? '#DCFCE7' : '#F1F5F9')" />
                  </div>
                </div>
                <q-markup-table flat bordered dense class="my_radio_less">
                  <thead class="bg-theme-soft">
                    <tr>
                      <th class="text-left">{{ $t('Date') }}</th>
                      <th class="text-left">{{ $t('Party') }}</th>
                      <th class="text-center">{{ $t('Direction') }}</th>
                      <th class="text-right">{{ $t('Amount') }}</th>
                      <th class="text-left">{{ $t('Basis') }}</th>
                      <th class="text-left">{{ $t('HandledBy') }}</th>
                      <th class="text-center">{{ $t('Status') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="pAccountsLoading">
                      <td colspan="7" class="text-center q-py-md"><q-spinner color="primary" size="2em" /></td>
                    </tr>
                    <tr v-else-if="pAccounts.length === 0">
                      <td colspan="7" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td>
                    </tr>
                    <tr v-for="t in pAccounts" :key="t.id">
                      <td style="white-space:nowrap">{{ t.tx_date ? t.tx_date.slice(0, 10) : '—' }}</td>
                      <td class="text-weight-medium">
                        {{ t.party?.name || '—' }}
                        <div v-if="t.party?.code" class="text-caption text-grey-6">{{ t.party.code }}</div>
                      </td>
                      <td class="text-center">
                        <q-chip dense size="sm" :color="t.direction === 'in' ? 'green-7' : 'deep-orange-6'" text-color="white">
                          <q-icon :name="t.direction === 'in' ? 'south_west' : 'north_east'" size="13px" class="q-mr-xs" />
                          {{ $t(t.direction === 'in' ? 'MoneyIn' : 'MoneyOut') }}
                        </q-chip>
                      </td>
                      <td class="text-right text-weight-medium">{{ fmtMoney(t.amount) }} {{ t.currency }}</td>
                      <td class="text-caption" style="max-width:200px">{{ t.basis || '—' }}</td>
                      <td class="text-caption">{{ t.handled_by || t.user?.name || '—' }}</td>
                      <td class="text-center">
                        <q-chip v-if="t.status === 'pending'" dense size="sm" color="amber-8" text-color="white">{{ $t('Pending') }}</q-chip>
                        <q-icon v-else name="check_circle" color="positive" size="16px" />
                      </td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </q-tab-panel>

              <!-- DOCUMENTS -->
              <q-tab-panel name="docs">
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2">{{ $t('Documents') }} ({{ docs.length }})</div>
                  <progress-btn color="teal" icon="upload_file" v-if="$can('document-create')" @click="openDoc()">
                    {{ $t('Upload') }}
                  </progress-btn>
                </div>
                <div v-if="docsLoading" class="text-center q-py-md"><q-spinner color="primary" size="2em" /></div>
                <div v-else-if="docs.length === 0" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</div>
                <div v-else class="row q-col-gutter-md">
                  <div class="col-6 col-sm-4 col-md-3" v-for="d in docs" :key="d.id">
                    <div class="doc-card" @click="openPreview(d)">
                      <div class="doc-card__thumb">
                        <img v-if="docThumbs[d.id]" :src="docThumbs[d.id]" :alt="d.title" />
                        <div v-else class="doc-card__icon"><q-icon :name="catIcon(d.category)" size="34px" :color="catColor(d.category)" /></div>
                      </div>
                      <div class="doc-card__meta">
                        <div class="doc-card__title">{{ d.title }}</div>
                        <div class="doc-card__sub">
                          <q-chip dense size="sm" :color="catColor(d.category)" text-color="white">{{ $t(catKey(d.category)) }}</q-chip>
                          <span class="text-caption text-grey-6">v{{ d.version }} · {{ fmtSize(d.size) }}</span>
                        </div>
                      </div>
                      <div class="doc-card__actions" @click.stop>
                        <q-btn size="sm" dense flat round icon="download" color="teal-8" :loading="downloadingId === d.id" @click="downloadDoc(d)" />
                        <q-btn size="sm" dense flat round icon="edit" color="blue-8" v-if="$can('document-edit')" @click="openDoc(d)" />
                        <q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('document-delete')" @click="removeDoc(d)" />
                      </div>
                    </div>
                  </div>
                </div>
              </q-tab-panel>
            </q-tab-panels>
          </q-card>
        </div>
      </div>
    </m-backgrounds>

    <!-- Cap-table (investment) modal -->
    <m-modal :showCM="investmentDialog" @update:showCM="investmentDialog = $event" card_style="width: 520px">
      <q-card class="bg-white">
        <n-header icon="account_balance">{{ investmentForm.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Participant') }}</n-header>
        <q-separator />
        <q-form @submit="saveInvestment">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12">
              <q-toggle v-model="investmentForm.is_company" :label="$t('CompanyItselfParticipant')" color="secondary" />
            </div>
            <div class="col-12" v-if="!investmentForm.is_company">
              <q-select outlined dense color="primary" label-color="primary" v-model="investmentForm.investor_id"
                :options="investorOptions" emit-value map-options :label="$t('Investor')"
                :rules="[v => !!v || $t('FieldIsRequired')]" hide-bottom-space>
                <template v-slot:prepend><q-icon name="diversity_3" color="primary" /></template>
              </q-select>
            </div>
            <div class="col-7 col-sm-5">
              <q-input outlined dense color="primary" type="number" step="any" v-model.number="investmentForm.capital" :label="$t('Capital')">
                <template #prepend><q-icon name="savings" color="primary" /></template>
              </q-input>
            </div>
            <div class="col-5 col-sm-3">
              <q-select outlined dense color="primary" v-model="investmentForm.currency" :options="['AFN','USD']" :label="$t('Currency')" @update:model-value="syncRate" />
            </div>
            <div class="col-12 col-sm-4">
              <q-input outlined dense color="primary" type="number" step="any" v-model.number="investmentForm.rate" :label="$t('Rate')">
                <template #prepend><q-icon name="currency_exchange" color="primary" /></template>
              </q-input>
            </div>
            <div class="col-6">
              <q-input outlined dense color="primary" type="number" step="any" min="0" max="100" v-model.number="investmentForm.profit_percent" :label="$t('ProfitPercent')">
                <template #prepend><q-icon name="pie_chart" color="primary" /></template>
              </q-input>
            </div>
            <div class="col-6">
              <q-input outlined dense color="primary" type="number" step="any" v-model.number="investmentForm.profit_received" :label="$t('ProfitReceived')" />
            </div>
            <div class="col-12">
              <q-input outlined dense color="primary" v-model="investmentForm.basis" :label="$t('Basis')" :hint="$t('BasisHint')" />
            </div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="savingInvestment" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Site modal -->
    <m-modal :showCM="siteDialog" @update:showCM="siteDialog = $event" card_style="width: 460px">
      <q-card class="bg-white">
        <n-header icon="place">{{ siteForm.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Site') }}</n-header>
        <q-separator />
        <q-form @submit="saveSite">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><n-name :name="siteForm.name" @update:name="siteForm.name = $event" icon="place" :label="$t('Name')" autofocus /></div>
            <div class="col-12 col-sm-6"><n-name :name="siteForm.location" @update:name="siteForm.location = $event" icon="map" :label="$t('Location')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="siteForm.in_charge" @update:name="siteForm.in_charge = $event" icon="engineering" :label="$t('InCharge')" :rules="[]" /></div>
            <div class="col-12"><q-toggle v-model="siteForm.active" :label="$t('Active')" color="primary" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="savingSite" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Milestone modal -->
    <m-modal :showCM="milestoneDialog" @update:showCM="milestoneDialog = $event" card_style="width: 480px">
      <q-card class="bg-white">
        <n-header icon="flag">{{ milestoneForm.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Milestone') }}</n-header>
        <q-separator />
        <q-form @submit="saveMilestone">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><n-name :name="milestoneForm.title" @update:name="milestoneForm.title = $event" icon="flag" :label="$t('Title')" autofocus /></div>
            <div class="col-12 col-sm-6">
              <shamsi-date v-model="milestoneForm.due_date" color="primary" :label="$t('DueDate')" />
            </div>
            <div class="col-12 col-sm-6">
              <q-select outlined dense color="primary" v-model="milestoneForm.status"
                :options="milestoneStatusOptions" emit-value map-options :label="$t('Status')" />
            </div>
            <div class="col-12">
              <div class="text-caption text-grey-7 q-mb-xs">{{ $t('Progress') }}: {{ milestoneForm.progress }}%</div>
              <q-slider v-model="milestoneForm.progress" :min="0" :max="100" :step="5" label color="primary" />
            </div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="milestoneForm.notes" :label="$t('Notes')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="savingMilestone" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Task modal -->
    <m-modal :showCM="taskDialog" @update:showCM="taskDialog = $event" card_style="width: 600px">
      <q-card class="bg-white">
        <n-header icon="checklist">{{ taskForm.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Task') }}</n-header>
        <q-separator />
        <q-form @submit="saveTask">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-8"><n-name :name="taskForm.title" @update:name="taskForm.title = $event" icon="checklist" :label="$t('Task')" autofocus /></div>
            <div class="col-12 col-sm-4">
              <q-select outlined dense color="primary" v-model="taskForm.phase" :options="phaseOptions"
                use-input new-value-mode="add-unique" :label="$t('Phase')">
                <template v-slot:prepend><q-icon name="layers" color="primary" /></template>
              </q-select>
            </div>
            <div class="col-12 col-sm-6">
              <q-select outlined dense color="primary" v-model="taskForm.assignee" :options="assigneeOptions"
                use-input new-value-mode="add-unique" input-debounce="0" @filter="filterAssignees" clearable
                :label="$t('TeamLeadOrTeam')" :hint="$t('TeamLeadHint')">
                <template #prepend><q-icon name="group" color="primary" /></template>
                <template #option="scope">
                  <q-item v-bind="scope.itemProps">
                    <q-item-section avatar><q-icon :name="isTeam(scope.opt) ? 'groups' : 'engineering'" :color="isTeam(scope.opt) ? 'teal-6' : 'blue-grey-6'" size="20px" /></q-item-section>
                    <q-item-section><q-item-label>{{ scope.opt }}</q-item-label></q-item-section>
                  </q-item>
                </template>
                <template #no-option><q-item><q-item-section class="text-grey">{{ $t('TypeNameOrTeam') }}</q-item-section></q-item></template>
              </q-select>
            </div>
            <div class="col-6 col-sm-3">
              <q-select outlined dense color="primary" v-model="taskForm.priority" :options="priorityOptions" emit-value map-options :label="$t('Priority')" />
            </div>
            <div class="col-6 col-sm-3">
              <q-select outlined dense color="primary" v-model="taskForm.status" :options="taskStatusOptions" emit-value map-options :label="$t('Status')" />
            </div>
            <div class="col-12 col-sm-6">
              <q-select outlined dense color="primary" label-color="primary" v-model="taskForm.site_id"
                :options="siteOptions" emit-value map-options clearable :label="$t('Site')" />
            </div>
            <div class="col-6 col-sm-3">
              <shamsi-date v-model="taskForm.due_date" color="primary" :label="$t('DueDate')" clearable />
            </div>
            <div class="col-12">
              <div class="text-caption text-grey-7 q-mb-xs">{{ $t('Progress') }}: {{ taskForm.progress }}%</div>
              <q-slider v-model="taskForm.progress" :min="0" :max="100" :step="5" label color="primary" />
            </div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="taskForm.notes" :label="$t('Notes')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="savingTask" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Lifts modal (concrete pours / earthwork layers with inspection hold points) -->
    <m-modal :showCM="liftsDialog" @update:showCM="liftsDialog = $event" card_style="width: 860px">
      <q-card class="bg-white" v-if="liftTask">
        <n-header icon="layers" :subtitle="liftTask.title">{{ $t('Lifts') }}</n-header>
        <q-separator />
        <q-card-section class="q-pb-none">
          <div class="lift-note q-mb-sm">
            <q-icon name="info" size="15px" class="q-mr-xs" />
            {{ $t('LiftsNote') }}
          </div>
          <div class="row q-col-gutter-sm">
            <div class="col-6 col-sm-3"><stat-card dense icon="layers" :label="$t('Lifts')" :value="liftSummary.count" color="#0D9488" tint="#CCFBF1" /></div>
            <div class="col-6 col-sm-3"><stat-card dense icon="task_alt" :label="$t('Passed')" :value="liftSummary.passed" color="#16A34A" tint="#DCFCE7" /></div>
            <div class="col-6 col-sm-3"><stat-card dense icon="opacity" :label="$t('PouredQty')" :value="fmtMoney(liftSummary.poured_qty)" suffix="m³" color="#175A8C" tint="#E0EDF7" /></div>
            <div class="col-6 col-sm-3"><stat-card dense icon="report" :label="$t('Failed')" :value="liftSummary.failed" color="#DC2626" tint="#FEE2E2" /></div>
          </div>
        </q-card-section>
        <q-card-section>
          <div class="row items-center justify-between q-mb-xs">
            <div class="text-subtitle2">{{ $t('LiftLog') }}</div>
            <progress-btn color="teal" icon="add" v-if="$can('lift-create')" @click="addLift">{{ $t('AddLift') }}</progress-btn>
          </div>
          <q-markup-table flat bordered dense class="my_radio_less">
            <thead class="bg-theme-soft">
              <tr>
                <th class="text-left">{{ $t('Lift') }}</th><th class="text-left">{{ $t('Type') }}</th>
                <th class="text-right">{{ $t('Planned') }}</th><th class="text-right">{{ $t('Poured') }}</th>
                <th class="text-left">{{ $t('PourDate') }}</th><th class="text-center">{{ $t('Status') }}</th>
                <th class="text-left">{{ $t('Inspection') }}</th><th class="text-right">{{ $t('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!lifts.length"><td colspan="8" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td></tr>
              <tr v-for="l in lifts" :key="l.id" :class="{ 'lift-hold': liftSummary.hold_at && l.seq > liftSummary.hold_at }">
                <td class="text-weight-bold">#{{ l.seq }}</td>
                <td>{{ $t(liftTypeKey(l.lift_type)) }}<div v-if="l.description" class="text-caption text-grey-6">{{ l.description }}</div></td>
                <td class="text-right">{{ fmtMoney(l.planned_qty) }} {{ l.unit }}</td>
                <td class="text-right text-weight-medium">{{ l.poured_qty != null ? fmtMoney(l.poured_qty) + ' ' + l.unit : '—' }}</td>
                <td style="white-space:nowrap">{{ l.pour_date ? l.pour_date.slice(0, 10) : '—' }}</td>
                <td class="text-center"><q-chip dense size="sm" :color="liftStatusColor(l.status)" text-color="white">{{ $t(liftStatusKey(l.status)) }}</q-chip></td>
                <td class="text-caption">
                  <template v-if="l.inspection_result">
                    <q-icon :name="l.inspection_result === 'pass' ? 'check_circle' : 'cancel'" :color="l.inspection_result === 'pass' ? 'positive' : 'negative'" size="15px" />
                    {{ l.inspected_by }}<div v-if="l.inspection_note" class="text-grey-6">{{ l.inspection_note }}</div>
                  </template>
                  <span v-else class="text-grey-5">—</span>
                </td>
                <td class="text-right" style="white-space:nowrap">
                  <q-btn v-if="l.status === 'planned' && $can('lift-edit')" size="sm" dense flat round icon="opacity" color="blue-8" @click="openPour(l)"><q-tooltip>{{ $t('RecordPour') }}</q-tooltip></q-btn>
                  <q-btn v-if="l.status === 'poured' && $can('lift-edit')" size="sm" dense flat round icon="fact_check" color="teal-8" @click="openInspect(l)"><q-tooltip>{{ $t('Inspect') }}</q-tooltip></q-btn>
                  <q-btn v-if="$can('lift-delete')" size="sm" dense flat round icon="delete" color="negative" @click="removeLift(l)" />
                </td>
              </tr>
            </tbody>
          </q-markup-table>
        </q-card-section>
        <q-separator />
        <q-card-actions align="right" class="q-pa-sm"><q-btn flat :label="$t('Close')" color="grey-7" @click="liftsDialog = false" /></q-card-actions>
      </q-card>
    </m-modal>

    <!-- Record pour -->
    <m-modal :showCM="pourDialog" @update:showCM="pourDialog = $event" card_style="width: 420px">
      <q-card class="bg-white" v-if="liftEdit">
        <n-header icon="opacity">{{ $t('RecordPour') }} — {{ $t('Lift') }} #{{ liftEdit.seq }}</n-header><q-separator />
        <q-form @submit="savePour">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-7"><q-input outlined dense color="primary" type="number" step="any" v-model.number="pourForm.poured_qty" :label="$t('PouredQty') + ' (' + liftEdit.unit + ')'" /></div>
            <div class="col-5"><shamsi-date v-model="pourForm.pour_date" color="primary" :label="$t('PourDate')" /></div>
          </q-card-section>
          <q-separator /><n-submit :submitting="savingLift" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Inspect -->
    <m-modal :showCM="inspectDialog" @update:showCM="inspectDialog = $event" card_style="width: 440px">
      <q-card class="bg-white" v-if="liftEdit">
        <n-header icon="fact_check">{{ $t('Inspect') }} — {{ $t('Lift') }} #{{ liftEdit.seq }}</n-header><q-separator />
        <q-form @submit="saveInspect">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><q-btn-toggle spread v-model="inspectForm.inspection_result" unelevated toggle-color="primary" color="grey-2" text-color="grey-8" :options="[{ label: $t('Pass'), value: 'pass', icon: 'check' }, { label: $t('Fail'), value: 'fail', icon: 'close' }]" /></div>
            <div class="col-12"><n-name :name="inspectForm.inspected_by" @update:name="inspectForm.inspected_by = $event" icon="engineering" :label="$t('InspectedBy')" :rules="[]" /></div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="inspectForm.inspection_note" :label="$t('InspectionNote')" /></div>
          </q-card-section>
          <q-separator /><n-submit :submitting="savingLift" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Daily log modal -->
    <m-modal :showCM="logDialog" @update:showCM="logDialog = $event" card_style="width: 560px">
      <q-card class="bg-white">
        <n-header icon="assignment">{{ logForm.id ? $t('Edit') : $t('AddNew') }} — {{ $t('DailySiteLog') }}</n-header>
        <q-separator />
        <q-form @submit="saveLog">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6">
              <shamsi-date v-model="logForm.log_date" color="primary" :label="$t('LogDate')" />
            </div>
            <div class="col-12 col-sm-6">
              <q-select outlined dense color="primary" label-color="primary"
                v-model="logForm.site_id" :options="siteOptions" emit-value map-options clearable :label="$t('Site')">
                <template v-slot:prepend><q-icon name="place" color="primary" /></template>
              </q-select>
            </div>
            <div class="col-6 col-sm-4">
              <q-input outlined dense color="primary" type="number" v-model.number="logForm.labour_count" :label="$t('LabourCount')">
                <template #prepend><q-icon name="groups" color="primary" /></template>
              </q-input>
            </div>
            <div class="col-6 col-sm-8">
              <q-select outlined dense color="primary" v-model="logForm.weather"
                :options="weatherOptions" clearable use-input new-value-mode="add-unique" :label="$t('Weather')">
                <template v-slot:prepend><q-icon name="wb_sunny" color="primary" /></template>
              </q-select>
            </div>
            <div class="col-12">
              <q-input outlined dense color="primary" type="textarea" autogrow v-model="logForm.work_done" :label="$t('WorkDone')" />
            </div>
            <div class="col-12">
              <q-input outlined dense color="primary" type="textarea" autogrow v-model="logForm.notes" :label="$t('Notes')" />
            </div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="savingLog" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Subcontractor modal -->
    <m-modal :showCM="subDialog" @update:showCM="subDialog = $event" card_style="width: 560px">
      <q-card class="bg-white">
        <n-header icon="engineering">{{ subForm.id ? $t('Edit') : $t('AddNew') }} — {{ $t('Subcontractor') }}</n-header>
        <q-separator />
        <q-form @submit="saveSub">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6">
              <subcontractor-select v-model="subForm.tradesman_id" :label="$t('Name')" @selected="onSubPicked"
                :rules="[() => !!subForm.name || $t('FieldIsRequired')]" />
            </div>
            <div class="col-12 col-sm-6"><n-name :name="subForm.phone" @update:name="subForm.phone = $event" icon="phone" :label="$t('Phone')" :rules="[]" /></div>
            <div class="col-12 col-sm-6"><n-name :name="subForm.trade" @update:name="subForm.trade = $event" icon="handyman" :label="$t('Trade')" :rules="[]" /></div>
            <div class="col-6 col-sm-4">
              <q-input outlined dense color="primary" type="number" v-model.number="subForm.contract_amount" :label="$t('ContractAmount')">
                <template #prepend><q-icon name="payments" color="primary" /></template>
              </q-input>
            </div>
            <div class="col-6 col-sm-2">
              <q-select outlined dense color="primary" v-model="subForm.currency" :options="['AFN','USD','EUR','PKR','IRR']" :label="$t('Currency')" />
            </div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="subForm.scope" :label="$t('Scope')" /></div>
            <div class="col-12"><q-toggle v-model="subForm.active" :label="$t('Active')" color="primary" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="savingSub" :label="$t('Save')" />
        </q-form>
      </q-card>
    </m-modal>

    <!-- Payments modal -->
    <m-modal :showCM="paymentsDialog" @update:showCM="paymentsDialog = $event" card_style="width: 640px">
      <q-card class="bg-white" v-if="activeSub">
        <n-header icon="payments" :subtitle="activeSub.trade || ''">{{ activeSub.name }} — {{ $t('Payments') }}</n-header>
        <q-separator />

        <!-- Settlement summary -->
        <q-card-section class="q-pb-none">
          <div class="row q-col-gutter-sm">
            <div class="col-3" v-for="m in settlementCards" :key="m.label">
              <div class="settle-chip" :style="`border-color:${m.color}`">
                <div class="settle-chip__val" :style="`color:${m.color}`">{{ fmtMoney(m.value) }}</div>
                <div class="settle-chip__lbl">{{ $t(m.label) }}</div>
              </div>
            </div>
          </div>
        </q-card-section>

        <!-- Payments list -->
        <q-card-section>
          <q-markup-table flat bordered dense class="my_radio_less" style="max-height:230px">
            <thead class="bg-theme-soft">
              <tr>
                <th class="text-left">{{ $t('PaymentDate') }}</th>
                <th class="text-left">{{ $t('Kind') }}</th>
                <th class="text-right">{{ $t('Amount') }}</th>
                <th class="text-left">{{ $t('Notes') }}</th>
                <th class="text-right"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!activeSub.payments || activeSub.payments.length === 0">
                <td colspan="5" class="text-center text-grey-5 q-py-md">{{ $t('NoRecordFound') }}</td>
              </tr>
              <tr v-for="p in activeSub.payments" :key="p.id">
                <td style="white-space:nowrap">{{ p.payment_date ? p.payment_date.slice(0,10) : '—' }}</td>
                <td>
                  <q-chip dense size="sm" :color="p.kind === 'advance' ? 'orange-7' : 'positive'" text-color="white">
                    {{ p.kind === 'advance' ? $t('Advance') : $t('Payment') }}
                  </q-chip>
                </td>
                <td class="text-right text-weight-medium">{{ fmtMoney(p.amount) }} {{ p.currency }}</td>
                <td class="text-caption">{{ p.note || '—' }}</td>
                <td class="text-right">
                  <q-btn size="sm" dense flat round icon="delete" color="negative" v-if="$can('sub-payment-delete')" @click="removePayment(p)" />
                </td>
              </tr>
            </tbody>
          </q-markup-table>
        </q-card-section>

        <!-- Add payment -->
        <q-card-section class="q-pt-none" v-if="$can('sub-payment-create')">
          <q-separator class="q-mb-sm" />
          <div class="text-caption text-weight-bold text-grey-7 q-mb-xs">{{ $t('AddPayment') }}</div>
          <q-form @submit="savePayment" class="row q-col-gutter-sm items-end">
            <div class="col-6 col-sm-3">
              <shamsi-date v-model="paymentForm.payment_date" color="primary" :label="$t('PaymentDate')" />
            </div>
            <div class="col-6 col-sm-3">
              <q-select outlined dense color="primary" v-model="paymentForm.kind" :options="kindOptions" emit-value map-options :label="$t('Kind')" />
            </div>
            <div class="col-6 col-sm-3">
              <q-input outlined dense color="primary" type="number" v-model.number="paymentForm.amount" :label="$t('Amount')"
                :rules="[v => v > 0 || $t('FieldIsRequired')]" hide-bottom-space />
            </div>
            <div class="col-6 col-sm-2">
              <q-select outlined dense color="primary" v-model="paymentForm.currency" :options="['AFN','USD']" :label="$t('Currency')" />
            </div>
            <div class="col-12 col-sm-1">
              <q-btn unelevated color="teal-7" icon="add" type="submit" :loading="savingPayment" round dense />
            </div>
            <div class="col-12">
              <q-input outlined dense color="primary" v-model="paymentForm.note" :label="$t('Notes')" />
            </div>
          </q-form>
        </q-card-section>

        <q-separator />
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat :label="$t('Close')" color="grey-7" @click="paymentsDialog = false" />
        </q-card-actions>
      </q-card>
    </m-modal>

    <!-- Document upload / edit modal -->
    <m-modal :showCM="docDialog" @update:showCM="docDialog = $event" card_style="width: 520px">
      <q-card class="bg-white">
        <n-header icon="folder">{{ docForm.id ? $t('Edit') : $t('Upload') }} — {{ $t('Document') }}</n-header>
        <q-separator />
        <q-form @submit="saveDoc">
          <q-card-section class="row q-col-gutter-sm">
            <div class="col-12"><n-name :name="docForm.title" @update:name="docForm.title = $event" icon="title" :label="$t('Title')" autofocus /></div>
            <div class="col-12 col-sm-8">
              <q-select outlined dense color="primary" label-color="primary"
                v-model="docForm.category" :options="categoryOptions" emit-value map-options :label="$t('Category')">
                <template v-slot:prepend><q-icon name="category" color="primary" /></template>
              </q-select>
            </div>
            <div class="col-12 col-sm-4">
              <q-input outlined dense color="primary" type="number" min="1" v-model.number="docForm.version" :label="$t('Version')">
                <template #prepend><q-icon name="history" color="primary" /></template>
              </q-input>
            </div>
            <div class="col-12" v-if="!docForm.id">
              <q-file outlined dense color="primary" v-model="docFile" :label="$t('File')"
                accept=".pdf,.jpg,.jpeg,.png,.dwg,.doc,.docx,.xls,.xlsx,.zip" max-file-size="41943040"
                @rejected="onFileRejected">
                <template #prepend><q-icon name="attach_file" color="primary" /></template>
              </q-file>
              <div class="text-caption text-grey-6 q-mt-xs">{{ $t('MaxFileHint') }}</div>
            </div>
            <div class="col-12"><q-input outlined dense color="primary" type="textarea" autogrow v-model="docForm.notes" :label="$t('Notes')" /></div>
          </q-card-section>
          <q-separator />
          <n-submit :submitting="savingDoc" :label="docForm.id ? $t('Save') : $t('Upload')" />
        </q-form>
      </q-card>
    </m-modal>
    <!-- Document preview (visible, clickable, printable) -->
    <q-dialog v-model="previewDialog">
      <q-card class="bg-white doc-preview" v-if="previewDoc">
        <n-header icon="visibility" :subtitle="previewDoc.file_name || ''">{{ previewDoc.title }}</n-header>
        <q-separator />
        <q-card-section class="doc-preview__body">
          <img v-if="docThumbs[previewDoc.id]" :src="docThumbs[previewDoc.id]" :alt="previewDoc.title" />
          <div v-else class="doc-preview__nofile">
            <q-icon :name="catIcon(previewDoc.category)" size="64px" :color="catColor(previewDoc.category)" />
            <div class="text-caption text-grey-6 q-mt-sm">{{ previewDoc.file_name }} · {{ fmtSize(previewDoc.size) }}</div>
          </div>
        </q-card-section>
        <q-separator />
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat icon="print" color="primary" :label="$t('Print')" v-if="docThumbs[previewDoc.id]" @click="printDoc(previewDoc)" />
          <q-btn flat icon="download" color="teal-8" :label="$t('Download')" :loading="downloadingId === previewDoc.id" @click="downloadDoc(previewDoc)" />
          <q-btn flat :label="$t('Close')" color="grey-7" @click="previewDialog = false" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, getCurrentInstance, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { api } from '@/boot/axios'
import { compressImage } from '@/utils/image'
import { useCurrency } from '@/composables/useCurrency'
import { useLookups } from '@/composables/useLookups'

const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance()
const id = route.params.id

const project = ref({})
const tab = ref('overview')

// Professional section nav (small floating pills)
const sections = [
  { name: 'overview', label: 'Overview', icon: 'dashboard' },
  { name: 'captable', label: 'Financing', icon: 'account_balance', count: () => investments.value.length },
  { name: 'tasks', label: 'WorkBreakdown', icon: 'checklist', count: () => tasks.value.length },
  { name: 'sites', label: 'SiteOperations', icon: 'place', count: () => (project.value.sites || []).length },
  { name: 'milestones', label: 'Programme', icon: 'flag', count: () => (project.value.milestones || []).length },
  { name: 'logs', label: 'SiteDiary', icon: 'assignment', count: () => logs.value.length },
  { name: 'subs', label: 'Subcontracts', icon: 'engineering', count: () => subs.value.length },
  { name: 'accounts', label: 'PartyAccounts', icon: 'account_balance_wallet', count: () => pAccounts.value.length },
  { name: 'resources', label: 'PlantMaterials', icon: 'construction', count: () => resAssets.value.length + resMaterials.value.length },
  { name: 'docs', label: 'DrawingsDocs', icon: 'folder', count: () => docs.value.length },
]
const activeSection = computed(() => sections.find(s => s.name === tab.value) || sections[0])

// Overview cards: total + a small taste of the content; click jumps to section
const overviewCards = computed(() => [
  {
    section: 'captable', label: 'Financing', icon: 'account_balance', color: '#175A8C', tint: '#E0EDF7',
    total: investments.value.length,
    items: investments.value.slice(0, 3).map(r => ({ main: r.participant_name, sub: fmtCur(Number(r.capital) * Number(r.rate || 1)) })),
  },
  {
    section: 'tasks', label: 'WorkBreakdown', icon: 'checklist', color: '#0D9488', tint: '#CCFBF1',
    total: tasks.value.length,
    items: tasks.value.slice(0, 3).map(t => ({ main: t.title, sub: (t.progress || 0) + '%' })),
  },
  {
    section: 'milestones', label: 'Programme', icon: 'flag', color: '#7C3AED', tint: '#EDE9FE',
    total: (project.value.milestones || []).length,
    items: (project.value.milestones || []).slice(0, 3).map(m => ({ main: m.title, sub: m.due_date ? m.due_date.slice(0, 10) : '—' })),
  },
  {
    section: 'logs', label: 'SiteDiary', icon: 'assignment', color: '#D97706', tint: '#FEF3C7',
    total: logs.value.length,
    items: logs.value.slice(0, 3).map(l => ({ main: l.work_done || l.weather || '—', sub: l.log_date ? l.log_date.slice(0, 10) : '' })),
  },
  {
    section: 'subs', label: 'Subcontracts', icon: 'engineering', color: '#DC2626', tint: '#FEE2E2',
    total: subs.value.length,
    items: subs.value.slice(0, 3).map(s => ({ main: s.name, sub: s.trade || '' })),
  },
  {
    section: 'resources', label: 'PlantMaterials', icon: 'construction', color: '#475569', tint: '#E2E8F0',
    total: resAssets.value.length + resMaterials.value.length,
    items: [...resAssets.value.slice(0, 2).map(a => ({ main: a.asset?.name || '', sub: '×' + a.quantity })),
      ...resMaterials.value.slice(0, 1).map(m => ({ main: m.name, sub: fmtMoney(m.quantity) + ' ' + (m.unit || '') }))],
  },
])

const mapUrl = computed(() =>
  'https://maps.google.com/maps?q=' + encodeURIComponent(project.value.location || '') + '&z=13&output=embed'
)

// Arc gauges (attachment-style): physical, funding, programme
const gauges = computed(() => {
  const ms = project.value.milestones || []
  const msDone = ms.length ? (ms.filter(m => m.status === 'done').length / ms.length) * 100 : 0
  const tk = tasks.value
  const tkDone = tk.filter(t => t.status === 'done').length
  const fundedPct = fundedRatio.value * 100
  return [
    { label: 'Progress', pct: project.value.progress || 0, color: '#175A8C', tint: '#E0EDF7', up: true, sub: tkDone + '/' + tk.length },
    { label: 'CapitalRaised', pct: fundedPct, color: capitalGap.value > 0 ? '#EA580C' : '#16A34A', tint: capitalGap.value > 0 ? '#FFEDD5' : '#DCFCE7', up: fundedPct >= 100, sub: capitalGap.value > 0 ? '−' + fmtCur(capitalGap.value) : '100%' },
    { label: 'Programme', pct: msDone, color: '#7C3AED', tint: '#EDE9FE', up: true, sub: ms.filter(m => m.status === 'done').length + '/' + ms.length },
  ]
})

// Labour trend sparkline from the site diary (attachment-style live tile)
const labourSpark = computed(() => {
  const series = [...logs.value].reverse().slice(-14).map(l => Number(l.labour_count || 0))
  if (series.length < 2) return { points: '0,38 120,38', latest: series[0] ?? 0 }
  const max = Math.max(...series, 1)
  const step = 120 / (series.length - 1)
  const points = series.map((v, i) => (i * step).toFixed(1) + ',' + (38 - (v / max) * 34).toFixed(1)).join(' ')
  return { points, latest: series[series.length - 1] }
})

const photoDocs = computed(() => docs.value.filter(d => d.category === 'photo').slice(0, 8))

// ── Currency switcher (daily rate) ──
const displayCur = ref('AFN')
const dailyRate = ref(null) // AFN per 1 USD
async function loadRate () {
  try {
    // Response shape: { base: 'AFN', rates: { AFN: 1, USD: <AFN per 1 USD> } }
    const { data } = await api.get('/exchange-rates/current')
    dailyRate.value = Number(data?.rates?.USD ?? 0) || null
  } catch (_) {}
}
/** Convert an amount given in the project's currency to the display currency at the daily rate. */
function conv (v) {
  const from = project.value.currency || 'AFN'
  const to = displayCur.value
  let n = Number(v || 0)
  if (from !== to && dailyRate.value) {
    n = from === 'USD' && to === 'AFN' ? n * dailyRate.value : (from === 'AFN' && to === 'USD' ? n / dailyRate.value : n)
  }
  return n
}
function fmtCur (v) { return conv(v).toLocaleString('en-US', { maximumFractionDigits: 0 }) + ' ' + displayCur.value }

// ── Live activity feed ──
const activity = ref([])
const newActivityIds = ref(new Set())
let activityTimer = null
async function loadActivity (initial = false) {
  try {
    const { data } = await api.get('/projects/' + id + '/activity')
    if (!initial) {
      const known = new Set(activity.value.map(a => a.id))
      newActivityIds.value = new Set((data || []).filter(a => !known.has(a.id)).map(a => a.id))
      if (newActivityIds.value.size > 0) setTimeout(() => { newActivityIds.value = new Set() }, 6000)
    }
    activity.value = data || []
  } catch (_) {}
}

// ── Full A-Z printable report ──
function printFullReport () {
  const p = project.value
  const esc = (s) => String(s ?? '—').replace(/</g, '&lt;')
  const money = (v, c) => Number(v || 0).toLocaleString('en-US') + ' ' + (c || p.currency || '')
  const table = (title, heads, rows) => rows.length
    ? `<h2>${title}</h2><table><thead><tr>${heads.map(h => `<th>${h}</th>`).join('')}</tr></thead><tbody>${rows.map(r => `<tr>${r.map(c => `<td>${esc(c)}</td>`).join('')}</tr>`).join('')}</tbody></table>`
    : `<h2>${title}</h2><p class="empty">—</p>`

  const html = `<!DOCTYPE html><html dir="auto"><head><meta charset="utf-8"><title>${esc(p.name)}</title><style>
    body{font-family:Arial,'Segoe UI',sans-serif;margin:28px;color:#1E293B;font-size:12px}
    h1{color:#123A66;margin:0 0 2px;font-size:22px} .sub{color:#64748B;margin-bottom:14px}
    h2{color:#175A8C;font-size:14px;border-bottom:2px solid #E2E8F0;padding-bottom:4px;margin:18px 0 8px}
    table{border-collapse:collapse;width:100%;font-size:11.5px}
    th{background:#EEF4FB;text-align:start;padding:5px 7px;border:1px solid #CBD5E1}
    td{padding:5px 7px;border:1px solid #E2E8F0}
    .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:10px 0}
    .kpi{border:1px solid #E2E8F0;border-radius:8px;padding:8px}.kpi b{display:block;font-size:14px;color:#123A66}
    .empty{color:#94A3B8} @media print { button{display:none} }
  </style></head><body>
    <h1>${esc(p.name)}</h1>
    <div class="sub">${esc(p.code)} · Aria Herat Mohandes Zada · ${new Date().toLocaleDateString()}</div>
    <div class="grid">
      <div class="kpi">${'Contract'}<b>${money(p.contract_value)}</b></div>
      <div class="kpi">${'Raised'}<b>${money(funding.value.raised)}</b></div>
      <div class="kpi">${'Progress'}<b>${p.progress || 0}%</b></div>
      <div class="kpi">${'Status'}<b>${esc(statusLabel(p.status))}</b></div>
    </div>
    ${table('Project Information', ['Field', 'Value'], [
      ['Client', p.client_name], ['Location', p.location], ['Type', typeLabel(p.type)],
      ['Start', p.start_date ? p.start_date.slice(0, 10) : '—'], ['End', p.end_date ? p.end_date.slice(0, 10) : '—'],
      ['Branch', p.branch?.name], ['Description', p.description],
    ])}
    ${table('Financing — Cap Table', ['Participant', 'Capital', 'Profit %', 'Profit Received'],
      investments.value.map(r => [r.participant_name, money(r.capital, r.currency), Number(r.profit_percent) + '%', money(r.profit_received, r.currency)]))}
    ${table('Work Breakdown', ['Task', 'Phase', 'Assignee', 'Status', 'Progress'],
      tasks.value.map(t => [t.title, t.phase, t.assignee, t.status, (t.progress || 0) + '%']))}
    ${table('Site Operations', ['Site', 'Location', 'In Charge'],
      (p.sites || []).map(s => [s.name, s.location, s.in_charge]))}
    ${table('Programme — Milestones', ['Milestone', 'Due', 'Status', 'Progress'],
      (p.milestones || []).map(m => [m.title, m.due_date ? m.due_date.slice(0, 10) : '—', m.status, (m.progress || 0) + '%']))}
    ${table('Site Diary', ['Date', 'Labour', 'Weather', 'Work Done'],
      logs.value.map(l => [l.log_date ? l.log_date.slice(0, 10) : '—', l.labour_count, l.weather, l.work_done]))}
    ${table('Subcontracts', ['Name', 'Trade', 'Contract', 'Paid', 'Balance'],
      subs.value.map(s => [s.name, s.trade, money(s.contract_amount, s.currency), money(s.paid_total, s.currency), money(s.balance, s.currency)]))}
    ${table('Plant & Equipment', ['Asset', 'Category', 'Qty'],
      resAssets.value.map(a => [a.asset?.name, a.asset?.category, a.quantity]))}
    ${table('Materials', ['Material', 'Quantity', 'Unit'],
      resMaterials.value.map(m => [m.name, Number(m.quantity).toLocaleString(), m.unit]))}
    ${table('Drawings & Documents', ['Title', 'Category', 'Version', 'Size'],
      docs.value.map(d => [d.title, d.category, 'v' + d.version, fmtSize(d.size)]))}
    ${table('Recent Activity', ['When', 'User', 'Action', 'Description'],
      activity.value.map(a => [(a.created_at || '').slice(0, 16).replace('T', ' '), a.user?.name, a.action, a.description]))}
    <script>window.onload = () => window.print()<\/script>
  </body></html>`

  const w = window.open('', '_blank')
  if (!w) return
  w.document.write(html)
  w.document.close()
}

const typeLabel = (t) => (t === 'road' ? 'Road Building' : 'Building')
const statusLabel = (s) => ({
  planning: 'Planning', awaiting_funding: 'Awaiting Funding', active: 'Active',
  on_hold: 'On Hold', near_completion: 'Near Completion', completed: 'Completed',
  handover: 'Handover', cancelled: 'Cancelled',
}[s] ?? s)
function statusColor (s) {
  return {
    planning: 'blue-grey-5', awaiting_funding: 'amber-8', active: 'primary',
    on_hold: 'orange-8', near_completion: 'light-blue-7', completed: 'positive',
    handover: 'teal-7', cancelled: 'negative',
  }[s] ?? 'grey'
}

// Hero KPI tiles — system-driven progress + live funding roll-up,
// shown in the selected display currency at the daily rate.
const heroStats = computed(() => [
  { label: 'ContractValue', value: fmtCur(project.value.contract_value), icon: 'payments' },
  { label: 'CapitalRaised', value: fmtCur(funding.value.raised), icon: 'savings', sub: capitalGap.value > 0 ? ('−' + fmtCur(capitalGap.value)) : null },
  { label: 'Progress', value: (project.value.progress || 0) + '%', icon: 'trending_up' },
  { label: 'Resources', value: String(resAssets.value.length + resMaterials.value.length), icon: 'inventory_2' },
])

const facts = computed(() => [
  { label: 'Client', value: project.value.client_name || '—' },
  { label: 'Location', value: project.value.location || '—' },
  { label: 'Type', value: typeLabel(project.value.type) },
  { label: 'ContractValue', value: (Number(project.value.contract_value || 0)).toLocaleString('en-US') + ' ' + (project.value.currency || ''), class: 'text-primary' },
  { label: 'Status', value: statusLabel(project.value.status) },
  { label: 'StartDate', value: project.value.start_date ? project.value.start_date.slice(0,10) : '—' },
  { label: 'EndDate', value: project.value.end_date ? project.value.end_date.slice(0,10) : '—' },
  { label: 'Branch', value: project.value.branch?.name || '—' },
])

function mStatusColor (s) {
  return { pending: 'blue-grey-6', in_progress: 'amber-8', done: 'positive' }[s] ?? 'grey'
}
function mStatusKey (s) {
  return { pending: 'Pending', in_progress: 'InProgress', done: 'Done' }[s] ?? 'Pending'
}

const siteOptions = computed(() =>
  (project.value.sites || []).map(s => ({ label: s.name, value: s.id }))
)

// ── Cap Table (Investors & Capital) ──
const investments = ref([])
const investmentsLoading = ref(false)
const investmentDialog = ref(false)
const savingInvestment = ref(false)
const investorOptions = ref([])
const investmentForm = reactive({ id: null, is_company: false, investor_id: null, capital: null, currency: 'AFN', rate: 1, profit_percent: null, profit_received: 0, basis: '' })

// Live funding roll-up — capital and profit% summed independently, never derived.
const funding = computed(() => {
  const raised = investments.value.reduce((sum, r) => sum + Number(r.capital || 0) * Number(r.rate || 1), 0)
  const profit = investments.value.reduce((sum, r) => sum + Number(r.profit_percent || 0), 0)
  return {
    target: Number(project.value.contract_value || 0),
    raised,
    profit_allocated: profit,
    participants: investments.value.length,
  }
})
const capitalGap = computed(() => Math.max(0, funding.value.target - funding.value.raised))
const fundedRatio = computed(() => funding.value.target > 0 ? Math.min(1, funding.value.raised / funding.value.target) : 0)
const profitRemaining = computed(() => Math.round((100 - funding.value.profit_allocated) * 100) / 100)

async function loadInvestments () {
  investmentsLoading.value = true
  try {
    const { data } = await api.get('/projects/' + id + '/investments')
    investments.value = Array.isArray(data) ? data : []
  } catch (_) {} finally { investmentsLoading.value = false }
}
async function loadInvestorOptions () {
  try {
    const { data } = await api.get('/investors')
    investorOptions.value = (data || []).map(iv => ({ label: iv.name + ' (' + iv.code + ')', value: iv.id }))
  } catch (_) {}
}
function syncRate () {
  // Prefill the locked rate from today's daily rate; stays editable.
  investmentForm.rate = investmentForm.currency === 'AFN' ? 1 : (dailyRate.value || investmentForm.rate || 1)
}
function openInvestment (iv = null) {
  if (iv) Object.assign(investmentForm, { id: iv.id, is_company: !!iv.is_company, investor_id: iv.investor_id, capital: Number(iv.capital), currency: iv.currency || 'AFN', rate: Number(iv.rate || 1), profit_percent: Number(iv.profit_percent), profit_received: Number(iv.profit_received || 0), basis: iv.basis || '' })
  else Object.assign(investmentForm, { id: null, is_company: false, investor_id: null, capital: null, currency: 'AFN', rate: 1, profit_percent: null, profit_received: 0, basis: '' })
  investmentDialog.value = true
}
async function saveInvestment () {
  savingInvestment.value = true
  try {
    // Advisory only: an underfunded General Budget never blocks the client.
    if (investmentForm.is_company) {
      try {
        const { data: t } = await api.get('/treasury/summary')
        const share = Number(investmentForm.capital || 0) * Number(investmentForm.rate || 1)
        if (share > Number(t.available || 0)) {
          Notify.create({ type: 'warning', timeout: 6000, position: 'top',
            message: 'Company share exceeds the General Budget available balance (' + Number(t.available).toLocaleString() + ' ' + (t.base || '') + ') — recorded anyway.' })
        }
      } catch (_) {}
    }
    const payload = {
      is_company: investmentForm.is_company,
      investor_id: investmentForm.is_company ? null : investmentForm.investor_id,
      capital: investmentForm.capital || 0, currency: investmentForm.currency, rate: investmentForm.rate || 1,
      profit_percent: investmentForm.profit_percent || 0, profit_received: investmentForm.profit_received || 0,
      basis: investmentForm.basis,
    }
    if (investmentForm.id) await api.put('/investments/' + investmentForm.id, payload)
    else await api.post('/projects/' + id + '/investments', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    investmentDialog.value = false
    loadInvestments()
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { savingInvestment.value = false }
}
function removeInvestment (iv) { proxy.$delete('investments/' + iv.id, loadInvestments) }

// ── Resources (returnable assets + consumable materials) ──
const resAssets = ref([])
const resMaterials = ref([])
const resAssetOpen = ref(false)
const resMatOpen = ref(false)
const resAssetForm = reactive({ asset_id: null, quantity: 1 })
const resMatForm = reactive({ name: '', quantity: null, unit: 'bag' })
const unitOptions = computed(() => {
  const o = lookupOptions('unit')
  return o.length ? o.map(x => x.value) : ['bag', 'ton', 'm³', 'm²', 'm', 'piece', 'litre', 'roll', 'kg']
})
const assetCatalog = ref([])
const assetCatalogAll = ref([])

function assetIcon (c) { return { heavy_equipment: 'agriculture', vehicle: 'local_shipping', tool: 'handyman', equipment: 'construction' }[c] ?? 'inventory_2' }
async function loadResourceCatalog () {
  try {
    const { data } = await api.get('/assets')
    assetCatalogAll.value = (data || []).map(a => ({ label: a.name + ' (' + a.code + ')', value: a.id, available: a.available, category: a.category }))
    assetCatalog.value = assetCatalogAll.value
  } catch (_) {}
}
function filterAssets (val, update) {
  update(() => {
    const n = (val || '').toLowerCase()
    assetCatalog.value = n ? assetCatalogAll.value.filter(o => o.label.toLowerCase().includes(n)) : assetCatalogAll.value
  })
}
async function loadResources () {
  try { const { data } = await api.get('/projects/' + id + '/resources'); resAssets.value = data.assets || []; resMaterials.value = data.materials || [] } catch (_) {}
}
async function addResAsset () {
  if (!resAssetForm.asset_id) return Notify.create({ type: 'warning', message: 'Pick an asset' })
  try {
    await api.post('/projects/' + id + '/assets', { asset_id: resAssetForm.asset_id, quantity: resAssetForm.quantity || 1 })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    Object.assign(resAssetForm, { asset_id: null, quantity: 1 }); loadResources(); loadResourceCatalog()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) }
}
function removeResAsset (a) { proxy.$delete('project-assets/' + a.id, () => { loadResources(); loadResourceCatalog() }) }
async function addResMaterial () {
  try {
    await api.post('/projects/' + id + '/materials', { name: resMatForm.name, quantity: resMatForm.quantity || 0, unit: resMatForm.unit })
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    Object.assign(resMatForm, { name: '', quantity: null }); loadResources()
  } catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' }) }
}
function removeResMaterial (m) { proxy.$delete('project-materials/' + m.id, loadResources) }

async function load () {
  try {
    const { data } = await api.get('/projects/' + id)
    project.value = data
    displayCur.value = data.currency || 'AFN' // start in the project's own currency
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Load failed' })
    router.push('/projects')
  }
}

// ── Sites ──
const siteDialog = ref(false)
const savingSite = ref(false)
const siteForm = reactive({ id: null, name: '', location: '', in_charge: '', active: true })

function openSite (s = null) {
  if (s) Object.assign(siteForm, { id: s.id, name: s.name, location: s.location || '', in_charge: s.in_charge || '', active: !!s.active })
  else Object.assign(siteForm, { id: null, name: '', location: '', in_charge: '', active: true })
  siteDialog.value = true
}
async function saveSite () {
  savingSite.value = true
  try {
    const payload = { name: siteForm.name, location: siteForm.location, in_charge: siteForm.in_charge, active: siteForm.active }
    if (siteForm.id) await api.put('/sites/' + siteForm.id, payload)
    else await api.post('/projects/' + id + '/sites', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    siteDialog.value = false
    load()
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { savingSite.value = false }
}
function removeSite (s) { proxy.$delete('sites/' + s.id, load) }

// ── Milestones ──
const milestoneDialog = ref(false)
const savingMilestone = ref(false)
const milestoneForm = reactive({ id: null, title: '', due_date: '', status: 'pending', progress: 0, notes: '' })
const milestoneStatusOptions = [
  { label: 'Pending', value: 'pending' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Done', value: 'done' },
]

function openMilestone (m = null) {
  if (m) Object.assign(milestoneForm, { id: m.id, title: m.title, due_date: m.due_date ? m.due_date.slice(0,10) : '', status: m.status || 'pending', progress: m.progress || 0, notes: m.notes || '' })
  else Object.assign(milestoneForm, { id: null, title: '', due_date: '', status: 'pending', progress: 0, notes: '' })
  milestoneDialog.value = true
}
async function saveMilestone () {
  savingMilestone.value = true
  try {
    const payload = { title: milestoneForm.title, due_date: milestoneForm.due_date || null, status: milestoneForm.status, progress: milestoneForm.progress, notes: milestoneForm.notes }
    if (milestoneForm.id) await api.put('/milestones/' + milestoneForm.id, payload)
    else await api.post('/projects/' + id + '/milestones', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    milestoneDialog.value = false
    load()
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { savingMilestone.value = false }
}
function removeMilestone (m) { proxy.$delete('milestones/' + m.id, load) }

// ── Tasks (Work Breakdown) ──
const tasks = ref([])
const tasksLoading = ref(false)
const taskDialog = ref(false)
const savingTask = ref(false)
const priorityOptions = [
  { label: 'Low', value: 'low' },
  { label: 'Medium', value: 'medium' },
  { label: 'High', value: 'high' },
]
const taskStatusOptions = [
  { label: 'To Do', value: 'todo' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Done', value: 'done' },
  { label: 'Blocked', value: 'blocked' },
]
const taskForm = reactive({ id: null, title: '', phase: 'General', assignee: '', site_id: null, priority: 'medium', status: 'todo', progress: 0, due_date: '', notes: '' })

// Assign-to: searchable list of engineers/employees (the team lead), and you
// can also type a team name like "تیم برق" or "تیم سروی".
const employeeNames = ref([])
const teamSuggestions = ['تیم برق', 'تیم سروی', 'تیم نلدوانی', 'تیم آهنگری', 'تیم نجاری', 'تیم رنگمالی']
const assigneeOptions = ref([])
function isTeam (v) { return /تیم|team/i.test(String(v || '')) }
function allAssignees () { return [...employeeNames.value, ...teamSuggestions] }
function filterAssignees (val, update) {
  update(() => {
    const n = (val || '').toLowerCase()
    assigneeOptions.value = n ? allAssignees().filter(o => String(o).toLowerCase().includes(n)) : allAssignees()
  })
}
async function loadEmployeesForAssign () {
  try {
    const { data } = await api.get('/employees')
    const list = Array.isArray(data) ? data : (data.employees ?? data.data ?? [])
    employeeNames.value = list.map(e => e.full_name || e.name).filter(Boolean)
    assigneeOptions.value = allAssignees()
  } catch (_) { assigneeOptions.value = allAssignees() }
}

const phaseOptions = computed(() => {
  const set = new Set(['Foundation', 'Structure', 'Finishing', 'MEP', 'General'])
  tasks.value.forEach(t => { if (t.phase) set.add(t.phase) })
  return [...set]
})

const tasksByPhase = computed(() => {
  const map = new Map()
  for (const t of tasks.value) {
    const ph = t.phase || 'General'
    if (!map.has(ph)) map.set(ph, [])
    map.get(ph).push(t)
  }
  return [...map.entries()].map(([phase, items]) => ({
    phase, items, done: items.filter(i => i.status === 'done').length,
  }))
})

function priorityColor (p) { return { low: 'blue-grey-5', medium: 'amber-8', high: 'deep-orange-7' }[p] ?? 'grey' }
function priorityKey (p) { return { low: 'Low', medium: 'Medium', high: 'High' }[p] ?? 'Medium' }
function taskStatusColor (s) { return { todo: 'blue-grey-6', in_progress: 'primary', done: 'positive', blocked: 'negative' }[s] ?? 'grey' }
function taskStatusKey (s) { return { todo: 'ToDo', in_progress: 'InProgress', done: 'Done', blocked: 'Blocked' }[s] ?? 'ToDo' }

async function loadTasks () {
  tasksLoading.value = true
  try {
    const { data } = await api.get('/projects/' + id + '/tasks')
    tasks.value = Array.isArray(data) ? data : []
  } catch (_) {} finally { tasksLoading.value = false }
}

function openTask (t = null) {
  if (t) Object.assign(taskForm, { id: t.id, title: t.title, phase: t.phase || 'General', assignee: t.assignee || '', site_id: t.site_id, priority: t.priority || 'medium', status: t.status || 'todo', progress: t.progress || 0, due_date: t.due_date ? t.due_date.slice(0,10) : '', notes: t.notes || '' })
  else Object.assign(taskForm, { id: null, title: '', phase: 'General', assignee: '', site_id: null, priority: 'medium', status: 'todo', progress: 0, due_date: '', notes: '' })
  taskDialog.value = true
}
async function saveTask () {
  savingTask.value = true
  try {
    const payload = { title: taskForm.title, phase: taskForm.phase, assignee: taskForm.assignee, site_id: taskForm.site_id, priority: taskForm.priority, status: taskForm.status, progress: taskForm.progress, due_date: taskForm.due_date || null, notes: taskForm.notes }
    if (taskForm.id) await api.put('/tasks/' + taskForm.id, payload)
    else await api.post('/projects/' + id + '/tasks', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    taskDialog.value = false
    loadTasks()
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { savingTask.value = false }
}
function removeTask (t) { proxy.$delete('tasks/' + t.id, loadTasks) }

// ── Lifts (concrete pours / earthwork layers with inspection hold points) ──
const liftsDialog = ref(false)
const liftTask = ref(null)
const lifts = ref([])
const liftSummary = ref({ count: 0, passed: 0, failed: 0, poured: 0, poured_qty: 0, hold_at: null })
const pourDialog = ref(false)
const inspectDialog = ref(false)
const liftEdit = ref(null)
const savingLift = ref(false)
const pourForm = reactive({ poured_qty: null, pour_date: '' })
const inspectForm = reactive({ inspection_result: 'pass', inspected_by: '', inspection_note: '' })

function liftTypeKey (t) { return { concrete: 'Concrete', earthwork: 'Earthwork', scaffold: 'Scaffold', other: 'Other' }[t] ?? 'Concrete' }
function liftStatusKey (s) { return { planned: 'Planned', poured: 'Poured', passed: 'Passed', failed: 'Failed' }[s] ?? 'Planned' }
function liftStatusColor (s) { return { planned: 'blue-grey-5', poured: 'blue-7', passed: 'green-7', failed: 'red-7' }[s] ?? 'grey' }

function applyLifts (data) { lifts.value = data.lifts || []; liftSummary.value = data.summary || liftSummary.value }
async function openLifts (t) {
  liftTask.value = t; lifts.value = []; liftsDialog.value = true
  try { const { data } = await api.get('/tasks/' + t.id + '/lifts'); applyLifts(data) } catch (_) {}
}
async function addLift () {
  try { const { data } = await api.post('/tasks/' + liftTask.value.id + '/lifts', { lift_type: 'concrete', unit: 'm3', planned_qty: 12, height_m: 1.5 }); applyLifts(data); loadTasks() }
  catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) }
}
function openPour (l) { liftEdit.value = l; pourForm.poured_qty = Number(l.planned_qty || 0); pourForm.pour_date = today(); pourDialog.value = true }
async function savePour () {
  savingLift.value = true
  try { const { data } = await api.put('/lifts/' + liftEdit.value.id, { action: 'pour', ...pourForm }); applyLifts(data); pourDialog.value = false }
  catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) } finally { savingLift.value = false }
}
function openInspect (l) { liftEdit.value = l; Object.assign(inspectForm, { inspection_result: 'pass', inspected_by: '', inspection_note: '' }); inspectDialog.value = true }
async function saveInspect () {
  savingLift.value = true
  try { const { data } = await api.put('/lifts/' + liftEdit.value.id, { action: 'inspect', ...inspectForm }); applyLifts(data); inspectDialog.value = false }
  catch (e) { Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Failed' }) } finally { savingLift.value = false }
}
async function removeLift (l) {
  try { const { data } = await api.delete('/lifts/' + l.id); applyLifts(data); loadTasks() } catch (_) {}
}

// ── Daily Logs ──
const logs = ref([])
const logsLoading = ref(false)
const logDialog = ref(false)
const savingLog = ref(false)
const weatherOptions = computed(() => {
  const o = lookupOptions('weather')
  return o.length ? o.map(x => x.label) : ['Sunny', 'Cloudy', 'Rainy', 'Windy', 'Snow', 'Hot', 'Cold']
})
const logForm = reactive({ id: null, log_date: '', site_id: null, labour_count: 0, weather: null, work_done: '', notes: '' })

const today = () => new Date().toISOString().slice(0, 10)

async function loadLogs () {
  logsLoading.value = true
  try {
    const { data } = await api.get('/projects/' + id + '/site-logs')
    logs.value = Array.isArray(data) ? data : []
  } catch (_) {} finally { logsLoading.value = false }
}

function openLog (l = null) {
  if (l) Object.assign(logForm, { id: l.id, log_date: l.log_date ? l.log_date.slice(0,10) : today(), site_id: l.site_id, labour_count: l.labour_count || 0, weather: l.weather || null, work_done: l.work_done || '', notes: l.notes || '' })
  else Object.assign(logForm, { id: null, log_date: today(), site_id: null, labour_count: 0, weather: null, work_done: '', notes: '' })
  logDialog.value = true
}
async function saveLog () {
  savingLog.value = true
  try {
    const payload = { log_date: logForm.log_date, site_id: logForm.site_id, labour_count: logForm.labour_count || 0, weather: logForm.weather, work_done: logForm.work_done, notes: logForm.notes }
    if (logForm.id) await api.put('/site-logs/' + logForm.id, payload)
    else await api.post('/projects/' + id + '/site-logs', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    logDialog.value = false
    loadLogs()
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { savingLog.value = false }
}
function removeLog (l) { proxy.$delete('site-logs/' + l.id, loadLogs) }

// ── Subcontractors ──
const subs = ref([])
const subsLoading = ref(false)
const subDialog = ref(false)
const savingSub = ref(false)
const subForm = reactive({ id: null, tradesman_id: null, name: '', phone: '', trade: '', scope: '', contract_amount: null, currency: 'AFN', active: true })

// When a subcontractor is picked (or freshly registered) from the registry,
// carry its name across, and fill phone/trade when they're still blank.
function onSubPicked (t) {
  if (!t) { subForm.name = ''; return }
  subForm.name = t.name || ''
  if (!subForm.phone && t.phone) subForm.phone = t.phone
  if (!subForm.trade && t.trade) subForm.trade = t.trade
}

function fmtMoney (v) { return Number(v || 0).toLocaleString('en-US') }

async function loadSubs () {
  subsLoading.value = true
  try {
    const { data } = await api.get('/projects/' + id + '/subcontractors')
    subs.value = Array.isArray(data) ? data : []
  } catch (_) {} finally { subsLoading.value = false }
}

// ── Party accounts (who paid / who received inside this project) ──
const pAccounts = ref([])
const pAccountsLoading = ref(false)
const pAccSummary = ref({ in: 0, out: 0, net: 0, base: 'AFN' })
const { loadRates: loadCurrencyRates, smartMoney, ledgerTotals, netMoney } = useCurrency()
const { loadLookups, options: lookupOptions } = useLookups()

// Single-currency ledgers keep their own currency; mixed ones show the base
// total (locked rates) with a per-currency split under the figure.
const pAccCards = computed(() => {
  const { inBase, outBase, netBase, maps } = ledgerTotals(pAccounts.value)
  return {
    netBase,
    tin: smartMoney(inBase, maps.in),
    tout: smartMoney(outBase, maps.out),
    bal: netMoney(netBase, maps.net, proxy?.$t ? proxy.$t('Credit') : 'Credit', proxy?.$t ? proxy.$t('Debit') : 'Debit'),
  }
})

async function loadPAccounts () {
  pAccountsLoading.value = true
  try {
    const { data } = await api.get('/projects/' + id + '/party-transactions')
    pAccounts.value = data.transactions || []
    pAccSummary.value = data.summary || pAccSummary.value
  } catch (_) {} finally { pAccountsLoading.value = false }
}

function openSub (s = null) {
  if (s) Object.assign(subForm, { id: s.id, tradesman_id: s.tradesman_id || null, name: s.name, phone: s.phone || '', trade: s.trade || '', scope: s.scope || '', contract_amount: s.contract_amount, currency: s.currency || 'AFN', active: !!s.active })
  else Object.assign(subForm, { id: null, tradesman_id: null, name: '', phone: '', trade: '', scope: '', contract_amount: null, currency: 'AFN', active: true })
  subDialog.value = true
}
async function saveSub () {
  savingSub.value = true
  try {
    const payload = { name: subForm.name, tradesman_id: subForm.tradesman_id, phone: subForm.phone, trade: subForm.trade, scope: subForm.scope, contract_amount: subForm.contract_amount, currency: subForm.currency, active: subForm.active }
    if (subForm.id) await api.put('/subcontractors/' + subForm.id, payload)
    else await api.post('/projects/' + id + '/subcontractors', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    subDialog.value = false
    loadSubs()
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { savingSub.value = false }
}
function removeSub (s) { proxy.$delete('subcontractors/' + s.id, loadSubs) }

// ── Subcontractor payments ──
const paymentsDialog = ref(false)
const activeSub = ref(null)
const savingPayment = ref(false)
const kindOptions = [
  { label: 'Payment', value: 'payment' },
  { label: 'Advance', value: 'advance' },
]
const paymentForm = reactive({ payment_date: today(), kind: 'payment', amount: null, currency: 'AFN', note: '' })

const settlementCards = computed(() => {
  const s = activeSub.value || {}
  return [
    { label: 'ContractAmount', value: s.contract_amount, color: '#175A8C' },
    { label: 'Paid', value: s.paid_total, color: '#059669' },
    { label: 'Advance', value: s.advance_total, color: '#C2410C' },
    { label: 'Balance', value: s.balance, color: Number(s.balance) > 0 ? '#DC2626' : '#64748B' },
  ]
})

async function openPayments (s) {
  activeSub.value = { ...s, payments: [] }
  Object.assign(paymentForm, { payment_date: today(), kind: 'payment', amount: null, currency: s.currency || 'AFN', note: '' })
  paymentsDialog.value = true
  await refreshActiveSub(s.id)
}
async function refreshActiveSub (subId) {
  try {
    const { data } = await api.get('/subcontractors/' + subId)
    activeSub.value = data
  } catch (_) {}
}
async function savePayment () {
  if (!activeSub.value) return
  savingPayment.value = true
  try {
    const payload = { payment_date: paymentForm.payment_date, kind: paymentForm.kind, amount: paymentForm.amount, currency: paymentForm.currency, note: paymentForm.note }
    await api.post('/subcontractors/' + activeSub.value.id + '/payments', payload)
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    Object.assign(paymentForm, { amount: null, note: '' })
    await refreshActiveSub(activeSub.value.id)
    loadSubs()
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { savingPayment.value = false }
}
function removePayment (p) {
  proxy.$delete('subcontractor-payments/' + p.id, async () => {
    await refreshActiveSub(activeSub.value.id)
    loadSubs()
  })
}

// ── Documents ──
const docs = ref([])
const docsLoading = ref(false)
const docDialog = ref(false)
const savingDoc = ref(false)
const downloadingId = ref(null)
const docFile = ref(null)
const docForm = reactive({ id: null, title: '', category: 'drawing', version: 1, notes: '' })
const categoryOptions = [
  { label: 'Drawing', value: 'drawing' },
  { label: 'Contract', value: 'contract' },
  { label: 'Permit', value: 'permit' },
  { label: 'Photo', value: 'photo' },
  { label: 'Report', value: 'report' },
  { label: 'Other', value: 'other' },
]

const catMeta = {
  drawing: { icon: 'architecture', color: 'indigo-7', key: 'Drawing' },
  contract: { icon: 'description', color: 'blue-8', key: 'Contract' },
  permit: { icon: 'verified', color: 'teal-8', key: 'Permit' },
  photo: { icon: 'photo_camera', color: 'deep-orange-7', key: 'Photo' },
  report: { icon: 'summarize', color: 'purple-7', key: 'Report' },
  other: { icon: 'insert_drive_file', color: 'blue-grey-6', key: 'Other' },
}
function catIcon (c) { return (catMeta[c] || catMeta.other).icon }
function catColor (c) { return (catMeta[c] || catMeta.other).color }
function catKey (c) { return (catMeta[c] || catMeta.other).key }

function fmtSize (bytes) {
  const b = Number(bytes || 0)
  if (b < 1024) return b + ' B'
  if (b < 1048576) return (b / 1024).toFixed(1) + ' KB'
  return (b / 1048576).toFixed(1) + ' MB'
}

function onFileRejected () {
  Notify.create({ type: 'negative', message: 'File too large (max 20 MB) or type not allowed.' })
}

async function loadDocs () {
  docsLoading.value = true
  try {
    const { data } = await api.get('/projects/' + id + '/documents')
    docs.value = Array.isArray(data) ? data : []
    loadDocThumbs()
  } catch (_) {} finally { docsLoading.value = false }
}

// ── Visible documents: image thumbnails + click-to-preview + print ──
const docThumbs = reactive({})
const previewDialog = ref(false)
const previewDoc = ref(null)

async function loadDocThumbs () {
  for (const d of docs.value) {
    if (docThumbs[d.id] || !(d.mime_type || '').startsWith('image/')) continue
    try {
      const res = await api.get('/documents/' + d.id + '/download', { responseType: 'blob' })
      docThumbs[d.id] = URL.createObjectURL(new Blob([res.data], { type: d.mime_type }))
    } catch (_) {}
  }
}
function openPreview (d) { previewDoc.value = d; previewDialog.value = true }
function printDoc (d) {
  const src = docThumbs[d.id]
  if (!src) return
  const w = window.open('', '_blank')
  if (!w) return
  w.document.write('<html><head><title>' + d.title + '</title></head><body style="margin:0;text-align:center">' +
    '<img src="' + src + '" style="max-width:100%" onload="window.print()"></body></html>')
  w.document.close()
}

function openDoc (d = null) {
  docFile.value = null
  if (d) Object.assign(docForm, { id: d.id, title: d.title, category: d.category || 'other', version: d.version || 1, notes: d.notes || '' })
  else Object.assign(docForm, { id: null, title: '', category: 'drawing', version: 1, notes: '' })
  docDialog.value = true
}

async function saveDoc () {
  if (!docForm.id && !docFile.value) {
    return Notify.create({ type: 'warning', message: 'Please choose a file to upload' })
  }
  savingDoc.value = true
  try {
    if (docForm.id) {
      await api.put('/documents/' + docForm.id, { title: docForm.title, category: docForm.category, version: docForm.version, notes: docForm.notes })
    } else {
      const fd = new FormData()
      fd.append('file', await compressImage(docFile.value))
      fd.append('title', docForm.title)
      fd.append('category', docForm.category)
      fd.append('version', docForm.version)
      if (docForm.notes) fd.append('notes', docForm.notes)
      await api.post('/projects/' + id + '/documents', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    }
    Notify.create({ type: 'positive', position: 'bottom', icon: 'cloud_done', message: 'Saved' })
    docDialog.value = false
    loadDocs()
  } catch (e) {
    Notify.create({ type: 'negative', message: e?.response?.data?.message || 'Save failed' })
  } finally { savingDoc.value = false }
}

async function downloadDoc (d) {
  downloadingId.value = d.id
  try {
    const res = await api.get('/documents/' + d.id + '/download', { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = d.file_name || d.title
    document.body.appendChild(a); a.click(); a.remove()
    URL.revokeObjectURL(url)
  } catch (e) {
    Notify.create({ type: 'negative', message: 'Download failed' })
  } finally { downloadingId.value = null }
}

function removeDoc (d) { proxy.$delete('documents/' + d.id, loadDocs) }

onMounted(() => {
  loadLookups()
  load(); loadInvestments(); loadInvestorOptions(); loadResources(); loadResourceCatalog()
  loadTasks(); loadLogs(); loadSubs(); loadPAccounts(); loadDocs(); loadRate(); loadCurrencyRates(); loadActivity(true); loadEmployeesForAssign()
  // Anyone working anywhere in this project keeps the dashboard in sync.
  activityTimer = setInterval(() => { loadActivity() }, 30000)
})

onUnmounted(() => {
  if (activityTimer) clearInterval(activityTimer)
  Object.values(docThumbs).forEach(u => URL.revokeObjectURL(u))
})
</script>

<style scoped>
.phase-head {
  display: flex;
  align-items: center;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--q-primary);
  background: color-mix(in srgb, var(--q-primary) 8%, #fff);
  border-radius: 8px 8px 0 0;
  padding: 6px 10px;
  border: 1px solid #E2E8F0;
  border-bottom: none;
}
.lift-note {
  display: flex; align-items: center; font-size: 12px; color: #B45309;
  background: #FEF3C7; border: 1px dashed #F59E0B; border-radius: 8px; padding: 7px 10px;
}
.lift-hold td { background: color-mix(in srgb, #F59E0B 7%, #fff); }
.settle-chip {
  border: 1.5px solid #E2E8F0;
  border-radius: 10px;
  padding: 8px 10px;
  text-align: center;
  background: #F8FAFC;
}
.settle-chip__val { font-size: 15px; font-weight: 800; letter-spacing: -0.3px; }
.settle-chip__lbl { font-size: 10px; color: #94A3B8; margin-top: 2px; }
.meter-card {
  border: 1.5px solid #E2E8F0;
  border-radius: 12px;
  padding: 12px 14px;
  background: linear-gradient(180deg, #FBFDFF 0%, #F5F8FC 100%);
}

/* ── Redesigned project hero (immersive blueprint art) ── */
.proj-hero__bar {
  background: linear-gradient(135deg, #123A66 0%, #175A8C 55%, #1E6BA8 100%);
  border-radius: 14px;
  padding: 16px 18px;
  color: #fff;
  box-shadow: 0 10px 26px -12px rgba(18, 58, 102, 0.6);
  position: relative;
  overflow: hidden;
}
.proj-hero__bar > * { position: relative; z-index: 1; }
.proj-hero__bar::before {
  content: ''; position: absolute; inset: 0; z-index: 0; pointer-events: none;
  background-image:
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='620' height='220' viewBox='0 0 620 220' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cg stroke-opacity='0.10'%3E%3Cpath d='M40 220V120h70v100M55 140h40M55 165h40M55 190h40'/%3E%3Cpath d='M340 220V60h95v160M360 85h55M360 115h55M360 145h55M360 175h55'/%3E%3Cpath d='M470 220V100h75v120M488 125h39M488 155h39M488 185h39'/%3E%3C/g%3E%3Cg stroke-opacity='0.16'%3E%3Cpath d='M150 220V38h12V16l95 26v16h-62'/%3E%3Cpath d='M162 16h120M282 16v36M276 52h12'/%3E%3Ccircle cx='282' cy='62' r='7'/%3E%3C/g%3E%3C/svg%3E"),
    radial-gradient(circle at 85% 15%, rgba(200, 134, 45, 0.22), transparent 45%);
  background-repeat: no-repeat, no-repeat;
  background-position: right -20px bottom, center;
}
.proj-hero__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.proj-hero__title { display: flex; align-items: center; gap: 12px; }
.proj-hero__icon {
  width: 46px; height: 46px; border-radius: 12px;
  background: rgba(255, 255, 255, 0.14);
  display: flex; align-items: center; justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.25);
}
.proj-hero__name { font-size: 20px; font-weight: 800; letter-spacing: -0.3px; line-height: 1.15; }
.proj-hero__meta { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.proj-hero__code { font-size: 12px; font-family: monospace; opacity: 0.85; letter-spacing: 0.5px; }
.proj-hero__pill {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 11.5px; padding: 2px 8px; border-radius: 20px;
  background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.2);
}
.proj-hero__progress { margin-top: 14px; }
.proj-hero__track { opacity: 0.95; }
.proj-hero__stats { margin-top: 10px; }

/* ── Mobile: keep the hero tidy on small screens ── */
@media (max-width: 599px) {
  .proj-hero__bar { padding: 14px 14px; }
  .proj-hero__head { flex-direction: column; align-items: stretch; gap: 10px; }
  .proj-hero__title { gap: 10px; }
  .proj-hero__icon { width: 40px; height: 40px; border-radius: 11px; }
  .proj-hero__name { font-size: 17px; }
  .proj-hero__actions {
    justify-content: flex-start;
    flex-wrap: wrap;
    padding-top: 4px;
    border-top: 1px solid rgba(255, 255, 255, 0.14);
  }
  .proj-hero__actions :deep(.q-btn) { font-size: 11px; }
}
.kpi-tile {
  border: 1.5px solid #E2E8F0; border-radius: 12px; padding: 12px 14px;
  background: #fff; height: 100%;
}
.kpi-tile__icon { color: var(--q-primary); opacity: 0.85; }
.kpi-tile__val { font-size: 18px; font-weight: 800; letter-spacing: -0.3px; margin-top: 4px; color: #1E293B; }
.kpi-tile__lbl { font-size: 11px; color: #94A3B8; margin-top: 1px; }
.res-add { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 10px; }

/* ── Floating section pills (dreamy nav) ── */
.dash-nav {
  display: flex; align-items: center; gap: 4px;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(10px);
  border: 1px solid #E2E8F0;
  border-radius: 999px;
  padding: 5px 8px;
  box-shadow: 0 10px 30px -14px rgba(18, 58, 102, 0.35);
  width: fit-content; max-width: 100%;
  margin: 0 auto;
  overflow-x: auto;
  position: sticky; top: 8px; z-index: 10;
}
.dash-pill {
  display: flex; align-items: center; gap: 6px;
  border: none; background: transparent; cursor: pointer;
  padding: 5px 11px; border-radius: 999px;
  color: #64748B; font-size: 12px; font-weight: 700;
  transition: all 0.25s ease; white-space: nowrap;
}
.dash-pill__orb {
  position: relative; width: 24px; height: 24px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: #F1F5F9; transition: all 0.25s ease;
}
.dash-pill__count {
  font-size: 10px; background: #E2E8F0; color: #475569;
  border-radius: 10px; padding: 1px 6px; font-weight: 800;
}
.dash-pill--active {
  background: linear-gradient(135deg, #123A66, #1E6BA8);
  color: #fff;
  box-shadow: 0 6px 18px -6px rgba(18, 58, 102, 0.55);
}
.dash-pill--active .dash-pill__orb { background: rgba(255, 255, 255, 0.18); color: #fff; }
.dash-pill--active .dash-pill__count { background: rgba(255, 255, 255, 0.2); color: #fff; }
.dash-pill--active .dash-pill__orb::before,
.dash-pill--active .dash-pill__orb::after {
  content: ''; position: absolute; inset: 0; border-radius: 50%;
  border: 2px solid rgba(255, 255, 255, 0.55);
  animation: dashwave 2.2s ease-out infinite;
}
.dash-pill--active .dash-pill__orb::after { animation-delay: 1.1s; }
@keyframes dashwave {
  0% { transform: scale(1); opacity: 0.9; }
  100% { transform: scale(2); opacity: 0; }
}
.dash-body { border-radius: 14px; }
@media (max-width: 900px) { .dash-pill__label { display: none; } }

/* ── Overview cards ── */
.ov-card {
  border: 1.5px solid #E2E8F0; border-radius: 14px; background: #fff;
  padding: 12px 14px; cursor: pointer; height: 100%;
  transition: all 0.2s ease;
}
.ov-card:hover { border-color: var(--q-primary); transform: translateY(-2px); box-shadow: 0 12px 24px -16px rgba(18, 58, 102, 0.4); }
.ov-card--static { cursor: default; }
.ov-card--static:hover { transform: none; border-color: #E2E8F0; box-shadow: none; }
.ov-card__head { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.ov-card__icon { width: 30px; height: 30px; border-radius: 9px; display: flex; align-items: center; justify-content: center; }
.ov-card__title { font-size: 13px; font-weight: 800; color: #1E293B; }
.ov-card__count { font-size: 15px; font-weight: 800; color: var(--q-primary); }
.ov-card__row { display: flex; align-items: center; gap: 4px; padding: 3px 0; font-size: 12px; }
.ov-card__row-main { color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
.ov-card__row-sub { color: #94A3B8; font-size: 11px; white-space: nowrap; }

/* map */
.ov-map { border-radius: 10px; overflow: hidden; height: 200px; background: #F1F5F9; }
.ov-map iframe { width: 100%; height: 100%; border: 0; }
.ov-map__empty { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94A3B8; font-size: 12px; }

/* live activity */
.live-dot { width: 9px; height: 9px; border-radius: 50%; background: #22C55E; box-shadow: 0 0 0 rgba(34, 197, 94, 0.6); animation: livepulse 2s infinite; }
@keyframes livepulse {
  0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5); }
  70% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
  100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}
.act-feed { max-height: 300px; overflow-y: auto; }
.act-item { display: flex; gap: 8px; padding: 7px 4px; border-bottom: 1px dashed #F1F5F9; transition: background 0.6s ease; }
.act-item--new { background: color-mix(in srgb, #22C55E 10%, #fff); border-radius: 8px; }
.act-item__dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 5px; flex-shrink: 0; background: #CBD5E1; }
.act-item__dot--created { background: #22C55E; }
.act-item__dot--updated { background: #3B82F6; }
.act-item__dot--deleted { background: #EF4444; }
.act-item__text { font-size: 12px; color: #334155; line-height: 1.35; }
.act-item__meta { font-size: 10.5px; color: #94A3B8; margin-top: 1px; }

/* currency toggle in hero */
.cur-toggle { display: flex; background: rgba(255, 255, 255, 0.16); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 999px; padding: 2px; }
.cur-toggle button { border: none; background: transparent; color: rgba(255, 255, 255, 0.75); font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 999px; cursor: pointer; }
.cur-toggle button.active { background: #fff; color: #123A66; }

/* ── Gauge cards (attachment-style arc meters) ── */
.gauge-card {
  border: 1.5px solid #E2E8F0; border-radius: 16px; background: #fff;
  padding: 12px 14px; height: 100%; text-align: center; position: relative;
}
.gauge-card__top { display: flex; justify-content: flex-start; }
.gauge-card__delta {
  display: inline-flex; align-items: center; font-size: 11px; font-weight: 800;
  border-radius: 20px; padding: 2px 8px;
}
.gauge-card__val { font-size: 26px; font-weight: 800; letter-spacing: -0.8px; color: #0F172A; margin-top: 2px; }
.gauge-card__arc { width: 96px; height: 54px; margin-top: 4px; }
.gauge-card__spark { width: 100%; height: 40px; margin-top: 12px; }
.gauge-card__lbl { font-size: 11px; color: #94A3B8; font-weight: 700; margin-top: 2px; }

/* ── Site photos strip ── */
.photo-strip { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 4px; }
.photo-strip__item { flex: 0 0 auto; width: 120px; cursor: pointer; }
.photo-strip__item img { width: 120px; height: 78px; object-fit: cover; border-radius: 10px; border: 1.5px solid #E2E8F0; transition: transform 0.2s ease; }
.photo-strip__item:hover img { transform: scale(1.04); border-color: var(--q-primary); }
.photo-strip__ph { width: 120px; height: 78px; border-radius: 10px; border: 1.5px dashed #FDBA74; display: flex; align-items: center; justify-content: center; background: #FFF7ED; }
.photo-strip__cap { font-size: 10.5px; color: #64748B; margin-top: 3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* document cards (visible, clickable) */
.doc-card { border: 1.5px solid #E2E8F0; border-radius: 12px; overflow: hidden; background: #fff; cursor: pointer; transition: all 0.2s ease; }
.doc-card:hover { border-color: var(--q-primary); transform: translateY(-2px); box-shadow: 0 12px 24px -16px rgba(18, 58, 102, 0.4); }
.doc-card__thumb { height: 110px; background: #F8FAFC; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.doc-card__thumb img { width: 100%; height: 100%; object-fit: cover; }
.doc-card__meta { padding: 8px 10px 4px; }
.doc-card__title { font-size: 12.5px; font-weight: 700; color: #1E293B; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.doc-card__sub { display: flex; align-items: center; gap: 6px; margin-top: 3px; }
.doc-card__actions { display: flex; justify-content: flex-end; padding: 0 6px 6px; }
.doc-preview { width: 640px; max-width: 95vw; }
.doc-preview__body { text-align: center; max-height: 65vh; overflow: auto; }
.doc-preview__body img { max-width: 100%; border-radius: 8px; }
.doc-preview__nofile { padding: 40px 0; }

@media (prefers-color-scheme: dark) {
  .kpi-tile { background: #1E293B; border-color: #334155; }
  .kpi-tile__val { color: #F1F5F9; }
}
</style>
