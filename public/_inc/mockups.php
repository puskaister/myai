<?php
// Az appok illusztrációi (HTML/CSS makettek, nem képfájlok — minden kijelzőn élesek).
// A bennük látható nevek és számok kitalált mintaadatok.
declare(strict_types=1);

function mockup_booking(): void { ?>
<div class="phone-stage" aria-hidden="true">
  <div class="phone">
    <div class="screen">
      <div class="notch"></div>
      <div class="app-top"><b>Minta Gumiszerviz</b><span>Online időpontfoglalás</span></div>
      <div class="app-body">
        <div class="app-card">
          <div class="t">Szezonális gumicsere · 30 perc</div>
          <div class="mini-cal">
            <i>H</i><i>K</i><i>Sze</i><i>Cs</i><i>P</i><i>Szo</i><i>V</i>
            <s class="off">6</s><s>7</s><s>8</s><s class="sel">9</s><s>10</s><s>11</s><s class="off">12</s>
            <s>13</s><s>14</s><s class="off">15</s><s>16</s><s>17</s><s>18</s><s class="off">19</s>
          </div>
          <div class="mini-slots"><s>08:00</s><s>08:30</s><s class="sel">09:30</s><s>10:00</s><s>11:00</s><s>13:30</s><s>14:00</s><s>15:30</s></div>
        </div>
        <div class="app-card"><div class="t">Rendszám</div><div style="color:#6b7280">ABC-123</div></div>
        <div class="mini-btn">Időpont lefoglalása</div>
      </div>
    </div>
  </div>
</div>
<?php }

function mockup_evfordulok(): void { ?>
<div class="phone-stage" aria-hidden="true">
  <div class="phone tilt-r">
    <div class="screen">
      <div class="notch"></div>
      <div class="evf-top"><b>Évfordulók</b><span>Fontos dátumok, időben</span></div>
      <div class="evf-body">
        <div class="evf-hl"><div class="e">🎂</div><div><div class="w">HOLNAP</div><div class="t">Anya születésnapja</div><div class="s">66. születésnap · szerda</div></div></div>
        <div class="evf-m">OKTÓBER</div>
        <div class="evf-row"><div class="d"><b>4</b><i>okt</i></div><div><div class="t">🌷 Ferenc névnapja</div><div class="s">Névnap · szombat</div></div><div class="n">5 nap múlva</div></div>
        <div class="evf-row"><div class="d"><b>12</b><i>okt</i></div><div><div class="t">💍 Házassági évforduló</div><div class="s">10. évforduló · vasárnap</div></div><div class="n far">még 13 nap</div></div>
        <div class="evf-row"><div class="d"><b>28</b><i>okt</i></div><div><div class="t">🎂 Bence</div><div class="s">8. születésnap · kedd</div></div><div class="n far">még 29 nap</div></div>
        <div class="evf-m">NOVEMBER</div>
        <div class="evf-row"><div class="d"><b>1</b><i>nov</i></div><div><div class="t">🕯️ Mindenszentek</div><div class="s">Emléknap · szombat</div></div><div class="n far">még 33 nap</div></div>
        <div class="evf-fab">+</div>
      </div>
    </div>
  </div>
</div>
<?php }

function mockup_stats(): void { ?>
<div aria-hidden="true">
  <div class="browser">
    <div class="browser-bar"><i></i><i></i><i></i><span>a-weboldalad.hu/stats</span></div>
    <div class="dash-top">Látogatók<small>Utolsó 30 nap</small></div>
    <div class="dash-body">
      <div class="dash-tiles">
        <div><b>1 284</b><span>látogató</span></div>
        <div><b>3 902</b><span>megtekintés</span></div>
        <div><b>1 p 42</b><span>átlagos idő</span></div>
        <div><b>611</b><span>kattintás</span></div>
      </div>
      <div class="dash-chart">
        <div class="h">Oldalmegtekintések naponta</div>
        <div class="dash-bars"><i style="height:38%"></i><i style="height:52%"></i><i style="height:45%"></i><i style="height:61%"></i><i style="height:58%"></i><i style="height:30%"></i><i style="height:26%"></i><i style="height:49%"></i><i style="height:66%"></i><i style="height:72%"></i><i style="height:63%"></i><i style="height:80%"></i><i style="height:41%"></i><i style="height:35%"></i><i style="height:57%"></i><i style="height:74%"></i><i style="height:69%"></i><i style="height:88%"></i><i style="height:92%"></i><i style="height:47%"></i><i style="height:39%"></i></div>
      </div>
      <div class="dash-lists">
        <div><div class="h">Honnan jöttek</div><p><em style="width:100%"></em><span>google.com</span><span>412</span></p><p><em style="width:61%"></em><span>facebook.com</span><span>251</span></p><p><em style="width:23%"></em><span>instagram.com</span><span>96</span></p></div>
        <div><div class="h">Mire kattintottak</div><p><em style="width:100%"></em><span>Időpontfoglalás</span><span>188</span></p><p><em style="width:57%"></em><span>Telefonszám</span><span>107</span></p><p><em style="width:35%"></em><span>Árak</span><span>66</span></p></div>
      </div>
    </div>
  </div>
</div>
<?php }

function mockup_bevasarlolista(): void { ?>
<div class="phone-stage" aria-hidden="true">
  <div class="phone">
    <div class="screen">
      <div class="notch"></div>
      <div class="bev-top"><b>Bevásárlólista</b><span>Heti bevásárlás · 👥 3</span></div>
      <div class="bev-body">
        <div class="bev-add"><span>Mit kell venni?</span><i>🎤</i></div>
        <div class="bev-heard">„kenyér, tej meg két kiló alma”</div>
        <div class="bev-cat">🥬 ZÖLDSÉG, GYÜMÖLCS</div>
        <div class="bev-row"><s></s><b>Alma</b><em>2 kg</em></div>
        <div class="bev-row"><s></s><b>Paradicsom</b><em>1 kg</em></div>
        <div class="bev-cat">🥖 PÉKÁRU</div>
        <div class="bev-row"><s></s><b>Kenyér</b></div>
        <div class="bev-cat">🥛 TEJTERMÉK, TOJÁS</div>
        <div class="bev-row"><s></s><b>Tej</b><em>2 l</em></div>
        <div class="bev-row done"><s>✓</s><b>Tojás</b><em>10 db</em></div>
      </div>
    </div>
  </div>
</div>
<?php }

function mockup_ugyintezes(): void { ?>
<div class="phone-stage" aria-hidden="true">
  <div class="phone tilt-r">
    <div class="screen">
      <div class="notch"></div>
      <div class="ugy-top"><b>Ügyintézési Segéd</b><span><em>A-014</em> Virtuális ügyintéző</span></div>
      <div class="ugy-body">
        <div class="ugy-msg bot">Jó napot! Miben segíthetek?</div>
        <div class="ugy-msg user">Milyen papírok kellenek a költözéshez?</div>
        <div class="ugy-msg bot"><b>Lakcímváltozás bejelentése</b>
          <ol><li>Személyi igazolvány, lakcímkártya</li><li>Szállásadói hozzájárulás</li><li>Bejelentés online vagy személyesen</li></ol>
        </div>
        <div class="ugy-chips"><s>Időpontfoglalás</s><s>Díjak</s><s>Nyitvatartás</s></div>
        <div class="ugy-input"><span>Írja be a kérdését…</span><i>➤</i></div>
      </div>
    </div>
  </div>
</div>
<?php }
