<template>
  <q-layout view="hHh lpR fFf">
    <q-page-container>
      <q-page class="auth-page">
        <div class="auth-split">

          <!-- LEFT PANEL — Branding & Features -->
          <div class="auth-left">
            <div class="auth-left__inner">

              <!-- Logo -->
              <div class="auth-logo">
                <div class="auth-logo__icon">
                  <brand-mark size="30" />
                </div>
                <div>
                  <div class="auth-logo__name">Aria Herat ERP</div>
                  <div class="auth-logo__tagline">Aria Herat Mohandes Zada — Construction ERP</div>
                </div>
              </div>

              <!-- Hero headline -->
              <div class="auth-headline">
                We build roads.<br>We raise buildings.<br><span class="auth-headline__gold">One system runs it all.</span>
              </div>
              <div class="auth-sub">
                The engineering ERP of Aria Herat Mohandes Zada — projects, sites,
                financing, plant &amp; payroll, managed end to end.
              </div>

              <!-- Discipline badges -->
              <div class="auth-badges">
                <div class="auth-badge">
                  <div class="auth-badge__ring"><q-icon name="route" size="21px" /></div>
                  <div><div class="auth-badge__t">Road Building</div><div class="auth-badge__s">سرک‌سازی</div></div>
                </div>
                <div class="auth-badge">
                  <div class="auth-badge__ring"><q-icon name="apartment" size="21px" /></div>
                  <div><div class="auth-badge__t">Block Building</div><div class="auth-badge__s">بلاک‌سازی و تعمیرات</div></div>
                </div>
                <div class="auth-badge">
                  <div class="auth-badge__ring"><q-icon name="engineering" size="21px" /></div>
                  <div><div class="auth-badge__t">Site Engineering</div><div class="auth-badge__s">انجنیری ساحه</div></div>
                </div>
              </div>

            </div>

            <!-- Decorative circles + skyline art -->
            <div class="auth-circle auth-circle--1"></div>
            <div class="auth-circle auth-circle--2"></div>
            <div class="auth-skyline"></div>
          </div>

          <!-- RIGHT PANEL — Router view (login / company-create) -->
          <div class="auth-right">
            <div class="auth-right__inner">
              <router-view />
            </div>
            <div class="auth-right__footer">
              © {{ new Date().getFullYear() }} Aria Herat Mohandes Zada · All rights reserved
            </div>
          </div>

        </div>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<script setup>
import BrandMark from '@/components/general/BrandMark.vue'



</script>

<style scoped>
/* ── Page shell ─────────────────────────────────── */
.auth-page {
  height: 100vh;
  overflow: hidden;
  background: #F0F4F8;
}

.auth-split {
  display: grid;
  grid-template-columns: 1fr 420px;
  height: 100vh;
  overflow: hidden;
}
@media (max-width: 900px) {
  .auth-split { grid-template-columns: 1fr; }
  .auth-left { display: none; }
}

/* ── Left panel ─────────────────────────────────── */
.auth-left {
  background: linear-gradient(145deg, #0A1628 0%, #0D2B4E 40%, #123A66 70%, #8A5A1E 100%);
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
}

.auth-left__inner {
  position: relative;
  z-index: 2;
  max-width: 520px;
}

/* Logo */
.auth-logo {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 48px;
}
.auth-logo__icon {
  width: 52px; height: 52px;
  background: rgba(255,255,255,.12);
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid rgba(255,255,255,.2);
}
.auth-logo__name {
  font-size: 22px; font-weight: 800; color: #fff; letter-spacing: -0.3px;
}
.auth-logo__tagline {
  font-size: 12px; color: rgba(255,255,255,.5); margin-top: 2px;
}

/* Headline */
.auth-headline {
  font-size: 38px;
  font-weight: 800;
  color: #fff;
  line-height: 1.2;
  letter-spacing: -1px;
  margin-bottom: 18px;
}
.auth-sub {
  font-size: 15px;
  color: rgba(255,255,255,.65);
  line-height: 1.65;
  margin-bottom: 36px;
}

/* Feature pills */
.auth-features {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 40px;
}
.auth-feat {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 14px;
  color: rgba(255,255,255,.85);
}
.auth-feat__icon {
  width: 30px; height: 30px;
  background: rgba(255,255,255,.1);
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

/* Stats bar */
.auth-stats {
  display: flex;
  gap: 32px;
  margin-bottom: 32px;
  padding: 20px 24px;
  background: rgba(255,255,255,.08);
  border-radius: 16px;
  border: 1px solid rgba(255,255,255,.12);
}
.auth-stat__val {
  font-size: 26px; font-weight: 800; color: #fff; letter-spacing: -0.5px;
}
.auth-stat__lbl {
  font-size: 11px; color: rgba(255,255,255,.5); margin-top: 2px;
}

/* Business type bubbles */
.auth-biz-row {
  display: flex; flex-wrap: wrap; gap: 8px;
}
.auth-biz {
  padding: 5px 14px;
  background: rgba(255,255,255,.1);
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 20px;
  font-size: 12px;
  color: rgba(255,255,255,.75);
}

/* Decorative circles */
.auth-circle {
  position: absolute;
  border-radius: 50%;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.06);
  pointer-events: none;
}
.auth-circle--1 { width: 400px; height: 400px; top: -120px; right: -120px; }
.auth-circle--2 { width: 280px; height: 280px; bottom: 40px; left: -80px; }
.auth-circle--3 { width: 180px; height: 180px; bottom: 160px; right: 40px; }

/* ── Right panel ────────────────────────────────── */
.auth-right {
  background: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 32px;
  box-shadow: -4px 0 40px rgba(0,0,0,.08);
}
.auth-right__inner {
  width: 100%;
  max-width: 360px;
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}
.auth-right__footer {
  font-size: 11px;
  color: #94A3B8;
  text-align: center;
  padding-top: 24px;
}

/* ── Headline gold + badges ── */
.auth-headline__gold {
  background: linear-gradient(90deg, #E9B44C, #C8862D);
  -webkit-background-clip: text; background-clip: text; color: transparent;
}
.auth-badges { display: flex; gap: 12px; margin: 22px 0 4px; flex-wrap: wrap; }
.auth-badge {
  display: flex; align-items: center; gap: 10px;
  background: rgba(255, 255, 255, 0.07);
  border: 1px solid rgba(233, 180, 76, 0.35);
  border-radius: 14px; padding: 10px 14px;
  backdrop-filter: blur(6px);
}
.auth-badge__ring {
  width: 40px; height: 40px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #E9B44C; border: 2px solid rgba(233, 180, 76, 0.65);
  background: rgba(233, 180, 76, 0.12);
}
.auth-badge__t { font-size: 12.5px; font-weight: 800; color: #fff; letter-spacing: 0.02em; }
.auth-badge__s { font-size: 10.5px; color: rgba(255, 255, 255, 0.65); }

/* ── Skyline, crane & road art (pure SVG, no assets) ── */
.auth-skyline {
  position: absolute; inset-inline: 0; bottom: 0; height: 58%;
  z-index: 1; pointer-events: none;
  background: url("data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%221300%22 height=%22380%22 viewBox=%220 0 1300 380%22 preserveAspectRatio=%22xMidYMax slice%22%3E %3Cg fill=%22%23ffffff%22 fill-opacity=%220.045%22%3E %3Cpath d=%22M0 380V210l40-18v-30h55v40l30 12v166zM140 380V150h70v230zM230 380V240l50-45 50 45v140zM980 380V190h60l20 25v165zM1080 380V235h90v145zM1190 380V170l35-20 35 20v210z%22/%3E %3C/g%3E %3Cg fill=%22none%22 stroke=%22%23ffffff%22 stroke-opacity=%220.14%22 stroke-width=%222%22%3E %3Cpath d=%22M60 380V140h90v240M75 165h60M75 195h60M75 225h60M75 255h60M75 285h60M75 315h60M75 345h60%22/%3E %3Cpath d=%22M175 380V90h110v290M195 115h70M195 150h70M195 185h70M195 220h70M195 255h70M195 290h70M195 325h70M230 90V60h20v30%22/%3E %3Cpath d=%22M310 380V180h80v200M325 205h50M325 235h50M325 265h50M325 295h50M325 325h50%22/%3E %3Cpath d=%22M840 380V120h95v260M858 145h60M858 180h60M858 215h60M858 250h60M858 285h60M858 320h60%22/%3E %3Cpath d=%22M955 380V220h75v160M970 245h45M970 275h45M970 305h45M970 335h45%22/%3E %3Cpath d=%22M1055 380V160h20l15-25 15 25h20v220M1070 190h50M1070 225h50M1070 260h50M1070 295h50M1070 330h50%22/%3E %3C/g%3E %3Cg fill=%22none%22 stroke=%22%23ffffff%22 stroke-opacity=%220.20%22 stroke-width=%222%22%3E %3Cpath d=%22M1180 380V120M1180 120q-14 0-14-16t14-16q14 0 14 16t-14 16M1166 150h28M1166 190h28M1166 230h28M1166 270h28%22/%3E %3Cpath d=%22M1235 380V150M1235 150q-12 0-12-14t12-14q12 0 12 14t-12 14M1223 180h24M1223 215h24M1223 250h24%22/%3E %3C/g%3E %3Cg fill=%22none%22 stroke=%22%23E9B44C%22 stroke-opacity=%220.30%22 stroke-width=%221.6%22%3E %3Cpath d=%22M420 380V150h100v230M420 195h100M420 240h100M420 285h100M420 330h100M445 150v230M470 150v230M495 150v230%22/%3E %3Cpath d=%22M420 195l25-45M445 195l25-45M470 195l25-45M420 240l25-45M445 240l25-45M470 240l25-45M495 240l25-45%22/%3E %3C/g%3E %3Cg stroke=%22%23E9B44C%22 stroke-opacity=%220.55%22 fill=%22none%22 stroke-width=%222.5%22%3E %3Cpath d=%22M585 380V60M600 380V60M585 90h15M585 130h15M585 170h15M585 210h15M585 250h15M585 290h15M585 330h15M585 90l15 40M600 90l-15 40M585 170l15 40M600 170l-15 40M585 250l15 40M600 250l-15 40%22/%3E %3Cpath d=%22M545 60h235M545 60l-30 25M545 60v18h-30M780 60v34M772 94h16%22/%3E %3Cpath d=%22M520 60h25v22h-25z%22/%3E %3Ccircle cx=%22780%22 cy=%22106%22 r=%229%22/%3E %3Cpath d=%22M760 122h40l-6 12h-28z%22/%3E %3C/g%3E %3Cg stroke=%22%23ffffff%22 stroke-opacity=%220.22%22 fill=%22none%22 stroke-width=%222%22%3E %3Cpath d=%22M110 190V80M110 80h90M110 80l-18 16M200 80v24M194 108h12M110 96h-22%22/%3E %3C/g%3E %3Cg%3E %3Cpath d=%22M0 380 Q 340 316 680 336 T 1300 330 L 1300 380 Z%22 fill=%22%23ffffff%22 fill-opacity=%220.07%22/%3E %3Cpath d=%22M0 362 Q 340 300 680 320 T 1300 314%22 stroke=%22%23E9B44C%22 stroke-opacity=%220.6%22 stroke-width=%223.5%22 stroke-dasharray=%2220 16%22 fill=%22none%22/%3E %3Cpath d=%22M0 380 Q 340 330 680 350 T 1300 346%22 stroke=%22%23ffffff%22 stroke-opacity=%220.18%22 stroke-width=%222%22 fill=%22none%22/%3E %3C/g%3E %3Cg fill=%22%23E9B44C%22 fill-opacity=%220.5%22%3E %3Cpath d=%22M700 318l6-16h30l10 10h18v14h-64zM712 332a6 6 0 1 0 0.1 0M746 332a6 6 0 1 0 0.1 0%22/%3E %3Cpath d=%22M330 344h34l-4-12h-20l-6 6zM322 344l-14-10h10l8 6zM338 356a6 6 0 1 0 0.1 0M356 356a6 6 0 1 0 0.1 0%22/%3E %3C/g%3E %3Cg stroke=%22%23ffffff%22 stroke-opacity=%220.35%22 stroke-width=%222%22 fill=%22none%22%3E %3Cpath d=%22M900 70q6-8 12 0q6-8 12 0M950 95q5-7 10 0q5-7 10 0M860 55q5-7 10 0q5-7 10 0%22/%3E %3C/g%3E %3C/svg%3E") no-repeat bottom center / cover;
}
</style>
