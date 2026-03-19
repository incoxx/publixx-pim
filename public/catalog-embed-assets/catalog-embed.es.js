/**
* @vue/shared v3.5.30
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
// @__NO_SIDE_EFFECTS__
function On(e) {
  const t = /* @__PURE__ */ Object.create(null);
  for (const s of e.split(",")) t[s] = 1;
  return (s) => s in t;
}
const ae = {}, It = [], et = () => {
}, Jr = () => !1, js = (e) => e.charCodeAt(0) === 111 && e.charCodeAt(1) === 110 && // uppercase letter
(e.charCodeAt(2) > 122 || e.charCodeAt(2) < 97), Hn = (e) => e.startsWith("onUpdate:"), ve = Object.assign, Fn = (e, t) => {
  const s = e.indexOf(t);
  s > -1 && e.splice(s, 1);
}, _o = Object.prototype.hasOwnProperty, ie = (e, t) => _o.call(e, t), G = Array.isArray, Ot = (e) => as(e) === "[object Map]", Ns = (e) => as(e) === "[object Set]", rr = (e) => as(e) === "[object Date]", te = (e) => typeof e == "function", _e = (e) => typeof e == "string", tt = (e) => typeof e == "symbol", oe = (e) => e !== null && typeof e == "object", zr = (e) => (oe(e) || te(e)) && te(e.then) && te(e.catch), Zr = Object.prototype.toString, as = (e) => Zr.call(e), go = (e) => as(e).slice(8, -1), Yr = (e) => as(e) === "[object Object]", Dn = (e) => _e(e) && e !== "NaN" && e[0] !== "-" && "" + parseInt(e, 10) === e, Jt = /* @__PURE__ */ On(
  // the leading comma is intentional so empty string "" is also included
  ",key,ref,ref_for,ref_key,onVnodeBeforeMount,onVnodeMounted,onVnodeBeforeUpdate,onVnodeUpdated,onVnodeBeforeUnmount,onVnodeUnmounted"
), Vs = (e) => {
  const t = /* @__PURE__ */ Object.create(null);
  return ((s) => t[s] || (t[s] = e(s)));
}, mo = /-\w/g, je = Vs(
  (e) => e.replace(mo, (t) => t.slice(1).toUpperCase())
), vo = /\B([A-Z])/g, Tt = Vs(
  (e) => e.replace(vo, "-$1").toLowerCase()
), Qr = Vs((e) => e.charAt(0).toUpperCase() + e.slice(1)), tn = Vs(
  (e) => e ? `on${Qr(e)}` : ""
), Xe = (e, t) => !Object.is(e, t), ws = (e, ...t) => {
  for (let s = 0; s < e.length; s++)
    e[s](...t);
}, Xr = (e, t, s, n = !1) => {
  Object.defineProperty(e, t, {
    configurable: !0,
    enumerable: !1,
    writable: n,
    value: s
  });
}, Rn = (e) => {
  const t = parseFloat(e);
  return isNaN(t) ? e : t;
}, yo = (e) => {
  const t = _e(e) ? Number(e) : NaN;
  return isNaN(t) ? e : t;
};
let ir;
const Ws = () => ir || (ir = typeof globalThis < "u" ? globalThis : typeof self < "u" ? self : typeof window < "u" ? window : typeof global < "u" ? global : {});
function jn(e) {
  if (G(e)) {
    const t = {};
    for (let s = 0; s < e.length; s++) {
      const n = e[s], r = _e(n) ? ko(n) : jn(n);
      if (r)
        for (const i in r)
          t[i] = r[i];
    }
    return t;
  } else if (_e(e) || oe(e))
    return e;
}
const xo = /;(?![^(]*\))/g, bo = /:([^]+)/, wo = /\/\*[^]*?\*\//g;
function ko(e) {
  const t = {};
  return e.replace(wo, "").split(xo).forEach((s) => {
    if (s) {
      const n = s.split(bo);
      n.length > 1 && (t[n[0].trim()] = n[1].trim());
    }
  }), t;
}
function he(e) {
  let t = "";
  if (_e(e))
    t = e;
  else if (G(e))
    for (let s = 0; s < e.length; s++) {
      const n = he(e[s]);
      n && (t += n + " ");
    }
  else if (oe(e))
    for (const s in e)
      e[s] && (t += s + " ");
  return t.trim();
}
const Co = "itemscope,allowfullscreen,formnovalidate,ismap,nomodule,novalidate,readonly", $o = /* @__PURE__ */ On(Co);
function ei(e) {
  return !!e || e === "";
}
function To(e, t) {
  if (e.length !== t.length) return !1;
  let s = !0;
  for (let n = 0; s && n < e.length; n++)
    s = us(e[n], t[n]);
  return s;
}
function us(e, t) {
  if (e === t) return !0;
  let s = rr(e), n = rr(t);
  if (s || n)
    return s && n ? e.getTime() === t.getTime() : !1;
  if (s = tt(e), n = tt(t), s || n)
    return e === t;
  if (s = G(e), n = G(t), s || n)
    return s && n ? To(e, t) : !1;
  if (s = oe(e), n = oe(t), s || n) {
    if (!s || !n)
      return !1;
    const r = Object.keys(e).length, i = Object.keys(t).length;
    if (r !== i)
      return !1;
    for (const o in e) {
      const l = e.hasOwnProperty(o), c = t.hasOwnProperty(o);
      if (l && !c || !l && c || !us(e[o], t[o]))
        return !1;
    }
  }
  return String(e) === String(t);
}
function ti(e, t) {
  return e.findIndex((s) => us(s, t));
}
const si = (e) => !!(e && e.__v_isRef === !0), A = (e) => _e(e) ? e : e == null ? "" : G(e) || oe(e) && (e.toString === Zr || !te(e.toString)) ? si(e) ? A(e.value) : JSON.stringify(e, ni, 2) : String(e), ni = (e, t) => si(t) ? ni(e, t.value) : Ot(t) ? {
  [`Map(${t.size})`]: [...t.entries()].reduce(
    (s, [n, r], i) => (s[sn(n, i) + " =>"] = r, s),
    {}
  )
} : Ns(t) ? {
  [`Set(${t.size})`]: [...t.values()].map((s) => sn(s))
} : tt(t) ? sn(t) : oe(t) && !G(t) && !Yr(t) ? String(t) : t, sn = (e, t = "") => {
  var s;
  return (
    // Symbol.description in es2019+ so we need to cast here to pass
    // the lib: es2016 check
    tt(e) ? `Symbol(${(s = e.description) != null ? s : t})` : e
  );
};
/**
* @vue/reactivity v3.5.30
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
let Le;
class So {
  // TODO isolatedDeclarations "__v_skip"
  constructor(t = !1) {
    this.detached = t, this._active = !0, this._on = 0, this.effects = [], this.cleanups = [], this._isPaused = !1, this.__v_skip = !0, this.parent = Le, !t && Le && (this.index = (Le.scopes || (Le.scopes = [])).push(
      this
    ) - 1);
  }
  get active() {
    return this._active;
  }
  pause() {
    if (this._active) {
      this._isPaused = !0;
      let t, s;
      if (this.scopes)
        for (t = 0, s = this.scopes.length; t < s; t++)
          this.scopes[t].pause();
      for (t = 0, s = this.effects.length; t < s; t++)
        this.effects[t].pause();
    }
  }
  /**
   * Resumes the effect scope, including all child scopes and effects.
   */
  resume() {
    if (this._active && this._isPaused) {
      this._isPaused = !1;
      let t, s;
      if (this.scopes)
        for (t = 0, s = this.scopes.length; t < s; t++)
          this.scopes[t].resume();
      for (t = 0, s = this.effects.length; t < s; t++)
        this.effects[t].resume();
    }
  }
  run(t) {
    if (this._active) {
      const s = Le;
      try {
        return Le = this, t();
      } finally {
        Le = s;
      }
    }
  }
  /**
   * This should only be called on non-detached scopes
   * @internal
   */
  on() {
    ++this._on === 1 && (this.prevScope = Le, Le = this);
  }
  /**
   * This should only be called on non-detached scopes
   * @internal
   */
  off() {
    this._on > 0 && --this._on === 0 && (Le = this.prevScope, this.prevScope = void 0);
  }
  stop(t) {
    if (this._active) {
      this._active = !1;
      let s, n;
      for (s = 0, n = this.effects.length; s < n; s++)
        this.effects[s].stop();
      for (this.effects.length = 0, s = 0, n = this.cleanups.length; s < n; s++)
        this.cleanups[s]();
      if (this.cleanups.length = 0, this.scopes) {
        for (s = 0, n = this.scopes.length; s < n; s++)
          this.scopes[s].stop(!0);
        this.scopes.length = 0;
      }
      if (!this.detached && this.parent && !t) {
        const r = this.parent.scopes.pop();
        r && r !== this && (this.parent.scopes[this.index] = r, r.index = this.index);
      }
      this.parent = void 0;
    }
  }
}
function Mo() {
  return Le;
}
let de;
const nn = /* @__PURE__ */ new WeakSet();
class ri {
  constructor(t) {
    this.fn = t, this.deps = void 0, this.depsTail = void 0, this.flags = 5, this.next = void 0, this.cleanup = void 0, this.scheduler = void 0, Le && Le.active && Le.effects.push(this);
  }
  pause() {
    this.flags |= 64;
  }
  resume() {
    this.flags & 64 && (this.flags &= -65, nn.has(this) && (nn.delete(this), this.trigger()));
  }
  /**
   * @internal
   */
  notify() {
    this.flags & 2 && !(this.flags & 32) || this.flags & 8 || oi(this);
  }
  run() {
    if (!(this.flags & 1))
      return this.fn();
    this.flags |= 2, or(this), li(this);
    const t = de, s = Ne;
    de = this, Ne = !0;
    try {
      return this.fn();
    } finally {
      ci(this), de = t, Ne = s, this.flags &= -3;
    }
  }
  stop() {
    if (this.flags & 1) {
      for (let t = this.deps; t; t = t.nextDep)
        Wn(t);
      this.deps = this.depsTail = void 0, or(this), this.onStop && this.onStop(), this.flags &= -2;
    }
  }
  trigger() {
    this.flags & 64 ? nn.add(this) : this.scheduler ? this.scheduler() : this.runIfDirty();
  }
  /**
   * @internal
   */
  runIfDirty() {
    gn(this) && this.run();
  }
  get dirty() {
    return gn(this);
  }
}
let ii = 0, zt, Zt;
function oi(e, t = !1) {
  if (e.flags |= 8, t) {
    e.next = Zt, Zt = e;
    return;
  }
  e.next = zt, zt = e;
}
function Nn() {
  ii++;
}
function Vn() {
  if (--ii > 0)
    return;
  if (Zt) {
    let t = Zt;
    for (Zt = void 0; t; ) {
      const s = t.next;
      t.next = void 0, t.flags &= -9, t = s;
    }
  }
  let e;
  for (; zt; ) {
    let t = zt;
    for (zt = void 0; t; ) {
      const s = t.next;
      if (t.next = void 0, t.flags &= -9, t.flags & 1)
        try {
          t.trigger();
        } catch (n) {
          e || (e = n);
        }
      t = s;
    }
  }
  if (e) throw e;
}
function li(e) {
  for (let t = e.deps; t; t = t.nextDep)
    t.version = -1, t.prevActiveLink = t.dep.activeLink, t.dep.activeLink = t;
}
function ci(e) {
  let t, s = e.depsTail, n = s;
  for (; n; ) {
    const r = n.prevDep;
    n.version === -1 ? (n === s && (s = r), Wn(n), Po(n)) : t = n, n.dep.activeLink = n.prevActiveLink, n.prevActiveLink = void 0, n = r;
  }
  e.deps = t, e.depsTail = s;
}
function gn(e) {
  for (let t = e.deps; t; t = t.nextDep)
    if (t.dep.version !== t.version || t.dep.computed && (ai(t.dep.computed) || t.dep.version !== t.version))
      return !0;
  return !!e._dirty;
}
function ai(e) {
  if (e.flags & 4 && !(e.flags & 16) || (e.flags &= -17, e.globalVersion === ss) || (e.globalVersion = ss, !e.isSSR && e.flags & 128 && (!e.deps && !e._dirty || !gn(e))))
    return;
  e.flags |= 2;
  const t = e.dep, s = de, n = Ne;
  de = e, Ne = !0;
  try {
    li(e);
    const r = e.fn(e._value);
    (t.version === 0 || Xe(r, e._value)) && (e.flags |= 128, e._value = r, t.version++);
  } catch (r) {
    throw t.version++, r;
  } finally {
    de = s, Ne = n, ci(e), e.flags &= -3;
  }
}
function Wn(e, t = !1) {
  const { dep: s, prevSub: n, nextSub: r } = e;
  if (n && (n.nextSub = r, e.prevSub = void 0), r && (r.prevSub = n, e.nextSub = void 0), s.subs === e && (s.subs = n, !n && s.computed)) {
    s.computed.flags &= -5;
    for (let i = s.computed.deps; i; i = i.nextDep)
      Wn(i, !0);
  }
  !t && !--s.sc && s.map && s.map.delete(s.key);
}
function Po(e) {
  const { prevDep: t, nextDep: s } = e;
  t && (t.nextDep = s, e.prevDep = void 0), s && (s.prevDep = t, e.nextDep = void 0);
}
let Ne = !0;
const ui = [];
function ct() {
  ui.push(Ne), Ne = !1;
}
function at() {
  const e = ui.pop();
  Ne = e === void 0 ? !0 : e;
}
function or(e) {
  const { cleanup: t } = e;
  if (e.cleanup = void 0, t) {
    const s = de;
    de = void 0;
    try {
      t();
    } finally {
      de = s;
    }
  }
}
let ss = 0;
class Lo {
  constructor(t, s) {
    this.sub = t, this.dep = s, this.version = s.version, this.nextDep = this.prevDep = this.nextSub = this.prevSub = this.prevActiveLink = void 0;
  }
}
class Bn {
  // TODO isolatedDeclarations "__v_skip"
  constructor(t) {
    this.computed = t, this.version = 0, this.activeLink = void 0, this.subs = void 0, this.map = void 0, this.key = void 0, this.sc = 0, this.__v_skip = !0;
  }
  track(t) {
    if (!de || !Ne || de === this.computed)
      return;
    let s = this.activeLink;
    if (s === void 0 || s.sub !== de)
      s = this.activeLink = new Lo(de, this), de.deps ? (s.prevDep = de.depsTail, de.depsTail.nextDep = s, de.depsTail = s) : de.deps = de.depsTail = s, fi(s);
    else if (s.version === -1 && (s.version = this.version, s.nextDep)) {
      const n = s.nextDep;
      n.prevDep = s.prevDep, s.prevDep && (s.prevDep.nextDep = n), s.prevDep = de.depsTail, s.nextDep = void 0, de.depsTail.nextDep = s, de.depsTail = s, de.deps === s && (de.deps = n);
    }
    return s;
  }
  trigger(t) {
    this.version++, ss++, this.notify(t);
  }
  notify(t) {
    Nn();
    try {
      for (let s = this.subs; s; s = s.prevSub)
        s.sub.notify() && s.sub.dep.notify();
    } finally {
      Vn();
    }
  }
}
function fi(e) {
  if (e.dep.sc++, e.sub.flags & 4) {
    const t = e.dep.computed;
    if (t && !e.dep.subs) {
      t.flags |= 20;
      for (let n = t.deps; n; n = n.nextDep)
        fi(n);
    }
    const s = e.dep.subs;
    s !== e && (e.prevSub = s, s && (s.nextSub = e)), e.dep.subs = e;
  }
}
const mn = /* @__PURE__ */ new WeakMap(), Ct = /* @__PURE__ */ Symbol(
  ""
), vn = /* @__PURE__ */ Symbol(
  ""
), ns = /* @__PURE__ */ Symbol(
  ""
);
function be(e, t, s) {
  if (Ne && de) {
    let n = mn.get(e);
    n || mn.set(e, n = /* @__PURE__ */ new Map());
    let r = n.get(s);
    r || (n.set(s, r = new Bn()), r.map = n, r.key = s), r.track();
  }
}
function ot(e, t, s, n, r, i) {
  const o = mn.get(e);
  if (!o) {
    ss++;
    return;
  }
  const l = (c) => {
    c && c.trigger();
  };
  if (Nn(), t === "clear")
    o.forEach(l);
  else {
    const c = G(e), u = c && Dn(s);
    if (c && s === "length") {
      const a = Number(n);
      o.forEach((d, y) => {
        (y === "length" || y === ns || !tt(y) && y >= a) && l(d);
      });
    } else
      switch ((s !== void 0 || o.has(void 0)) && l(o.get(s)), u && l(o.get(ns)), t) {
        case "add":
          c ? u && l(o.get("length")) : (l(o.get(Ct)), Ot(e) && l(o.get(vn)));
          break;
        case "delete":
          c || (l(o.get(Ct)), Ot(e) && l(o.get(vn)));
          break;
        case "set":
          Ot(e) && l(o.get(Ct));
          break;
      }
  }
  Vn();
}
function Mt(e) {
  const t = /* @__PURE__ */ re(e);
  return t === e ? t : (be(t, "iterate", ns), /* @__PURE__ */ De(e) ? t : t.map(Ve));
}
function Bs(e) {
  return be(e = /* @__PURE__ */ re(e), "iterate", ns), e;
}
function Ye(e, t) {
  return /* @__PURE__ */ ut(e) ? Rt(/* @__PURE__ */ $t(e) ? Ve(t) : t) : Ve(t);
}
const Ao = {
  __proto__: null,
  [Symbol.iterator]() {
    return rn(this, Symbol.iterator, (e) => Ye(this, e));
  },
  concat(...e) {
    return Mt(this).concat(
      ...e.map((t) => G(t) ? Mt(t) : t)
    );
  },
  entries() {
    return rn(this, "entries", (e) => (e[1] = Ye(this, e[1]), e));
  },
  every(e, t) {
    return st(this, "every", e, t, void 0, arguments);
  },
  filter(e, t) {
    return st(
      this,
      "filter",
      e,
      t,
      (s) => s.map((n) => Ye(this, n)),
      arguments
    );
  },
  find(e, t) {
    return st(
      this,
      "find",
      e,
      t,
      (s) => Ye(this, s),
      arguments
    );
  },
  findIndex(e, t) {
    return st(this, "findIndex", e, t, void 0, arguments);
  },
  findLast(e, t) {
    return st(
      this,
      "findLast",
      e,
      t,
      (s) => Ye(this, s),
      arguments
    );
  },
  findLastIndex(e, t) {
    return st(this, "findLastIndex", e, t, void 0, arguments);
  },
  // flat, flatMap could benefit from ARRAY_ITERATE but are not straight-forward to implement
  forEach(e, t) {
    return st(this, "forEach", e, t, void 0, arguments);
  },
  includes(...e) {
    return on(this, "includes", e);
  },
  indexOf(...e) {
    return on(this, "indexOf", e);
  },
  join(e) {
    return Mt(this).join(e);
  },
  // keys() iterator only reads `length`, no optimization required
  lastIndexOf(...e) {
    return on(this, "lastIndexOf", e);
  },
  map(e, t) {
    return st(this, "map", e, t, void 0, arguments);
  },
  pop() {
    return Wt(this, "pop");
  },
  push(...e) {
    return Wt(this, "push", e);
  },
  reduce(e, ...t) {
    return lr(this, "reduce", e, t);
  },
  reduceRight(e, ...t) {
    return lr(this, "reduceRight", e, t);
  },
  shift() {
    return Wt(this, "shift");
  },
  // slice could use ARRAY_ITERATE but also seems to beg for range tracking
  some(e, t) {
    return st(this, "some", e, t, void 0, arguments);
  },
  splice(...e) {
    return Wt(this, "splice", e);
  },
  toReversed() {
    return Mt(this).toReversed();
  },
  toSorted(e) {
    return Mt(this).toSorted(e);
  },
  toSpliced(...e) {
    return Mt(this).toSpliced(...e);
  },
  unshift(...e) {
    return Wt(this, "unshift", e);
  },
  values() {
    return rn(this, "values", (e) => Ye(this, e));
  }
};
function rn(e, t, s) {
  const n = Bs(e), r = n[t]();
  return n !== e && !/* @__PURE__ */ De(e) && (r._next = r.next, r.next = () => {
    const i = r._next();
    return i.done || (i.value = s(i.value)), i;
  }), r;
}
const Eo = Array.prototype;
function st(e, t, s, n, r, i) {
  const o = Bs(e), l = o !== e && !/* @__PURE__ */ De(e), c = o[t];
  if (c !== Eo[t]) {
    const d = c.apply(e, i);
    return l ? Ve(d) : d;
  }
  let u = s;
  o !== e && (l ? u = function(d, y) {
    return s.call(this, Ye(e, d), y, e);
  } : s.length > 2 && (u = function(d, y) {
    return s.call(this, d, y, e);
  }));
  const a = c.call(o, u, n);
  return l && r ? r(a) : a;
}
function lr(e, t, s, n) {
  const r = Bs(e), i = r !== e && !/* @__PURE__ */ De(e);
  let o = s, l = !1;
  r !== e && (i ? (l = n.length === 0, o = function(u, a, d) {
    return l && (l = !1, u = Ye(e, u)), s.call(this, u, Ye(e, a), d, e);
  }) : s.length > 3 && (o = function(u, a, d) {
    return s.call(this, u, a, d, e);
  }));
  const c = r[t](o, ...n);
  return l ? Ye(e, c) : c;
}
function on(e, t, s) {
  const n = /* @__PURE__ */ re(e);
  be(n, "iterate", ns);
  const r = n[t](...s);
  return (r === -1 || r === !1) && /* @__PURE__ */ Gn(s[0]) ? (s[0] = /* @__PURE__ */ re(s[0]), n[t](...s)) : r;
}
function Wt(e, t, s = []) {
  ct(), Nn();
  const n = (/* @__PURE__ */ re(e))[t].apply(e, s);
  return Vn(), at(), n;
}
const Io = /* @__PURE__ */ On("__proto__,__v_isRef,__isVue"), di = new Set(
  /* @__PURE__ */ Object.getOwnPropertyNames(Symbol).filter((e) => e !== "arguments" && e !== "caller").map((e) => Symbol[e]).filter(tt)
);
function Oo(e) {
  tt(e) || (e = String(e));
  const t = /* @__PURE__ */ re(this);
  return be(t, "has", e), t.hasOwnProperty(e);
}
class pi {
  constructor(t = !1, s = !1) {
    this._isReadonly = t, this._isShallow = s;
  }
  get(t, s, n) {
    if (s === "__v_skip") return t.__v_skip;
    const r = this._isReadonly, i = this._isShallow;
    if (s === "__v_isReactive")
      return !r;
    if (s === "__v_isReadonly")
      return r;
    if (s === "__v_isShallow")
      return i;
    if (s === "__v_raw")
      return n === (r ? i ? Uo : mi : i ? gi : _i).get(t) || // receiver is not the reactive proxy, but has the same prototype
      // this means the receiver is a user proxy of the reactive proxy
      Object.getPrototypeOf(t) === Object.getPrototypeOf(n) ? t : void 0;
    const o = G(t);
    if (!r) {
      let c;
      if (o && (c = Ao[s]))
        return c;
      if (s === "hasOwnProperty")
        return Oo;
    }
    const l = Reflect.get(
      t,
      s,
      // if this is a proxy wrapping a ref, return methods using the raw ref
      // as receiver so that we don't have to call `toRaw` on the ref in all
      // its class methods
      /* @__PURE__ */ we(t) ? t : n
    );
    if ((tt(s) ? di.has(s) : Io(s)) || (r || be(t, "get", s), i))
      return l;
    if (/* @__PURE__ */ we(l)) {
      const c = o && Dn(s) ? l : l.value;
      return r && oe(c) ? /* @__PURE__ */ xn(c) : c;
    }
    return oe(l) ? r ? /* @__PURE__ */ xn(l) : /* @__PURE__ */ Us(l) : l;
  }
}
class hi extends pi {
  constructor(t = !1) {
    super(!1, t);
  }
  set(t, s, n, r) {
    let i = t[s];
    const o = G(t) && Dn(s);
    if (!this._isShallow) {
      const u = /* @__PURE__ */ ut(i);
      if (!/* @__PURE__ */ De(n) && !/* @__PURE__ */ ut(n) && (i = /* @__PURE__ */ re(i), n = /* @__PURE__ */ re(n)), !o && /* @__PURE__ */ we(i) && !/* @__PURE__ */ we(n))
        return u || (i.value = n), !0;
    }
    const l = o ? Number(s) < t.length : ie(t, s), c = Reflect.set(
      t,
      s,
      n,
      /* @__PURE__ */ we(t) ? t : r
    );
    return t === /* @__PURE__ */ re(r) && (l ? Xe(n, i) && ot(t, "set", s, n) : ot(t, "add", s, n)), c;
  }
  deleteProperty(t, s) {
    const n = ie(t, s);
    t[s];
    const r = Reflect.deleteProperty(t, s);
    return r && n && ot(t, "delete", s, void 0), r;
  }
  has(t, s) {
    const n = Reflect.has(t, s);
    return (!tt(s) || !di.has(s)) && be(t, "has", s), n;
  }
  ownKeys(t) {
    return be(
      t,
      "iterate",
      G(t) ? "length" : Ct
    ), Reflect.ownKeys(t);
  }
}
class Ho extends pi {
  constructor(t = !1) {
    super(!0, t);
  }
  set(t, s) {
    return !0;
  }
  deleteProperty(t, s) {
    return !0;
  }
}
const Fo = /* @__PURE__ */ new hi(), Do = /* @__PURE__ */ new Ho(), Ro = /* @__PURE__ */ new hi(!0);
const yn = (e) => e, ms = (e) => Reflect.getPrototypeOf(e);
function jo(e, t, s) {
  return function(...n) {
    const r = this.__v_raw, i = /* @__PURE__ */ re(r), o = Ot(i), l = e === "entries" || e === Symbol.iterator && o, c = e === "keys" && o, u = r[e](...n), a = s ? yn : t ? Rt : Ve;
    return !t && be(
      i,
      "iterate",
      c ? vn : Ct
    ), ve(
      // inheriting all iterator properties
      Object.create(u),
      {
        // iterator protocol
        next() {
          const { value: d, done: y } = u.next();
          return y ? { value: d, done: y } : {
            value: l ? [a(d[0]), a(d[1])] : a(d),
            done: y
          };
        }
      }
    );
  };
}
function vs(e) {
  return function(...t) {
    return e === "delete" ? !1 : e === "clear" ? void 0 : this;
  };
}
function No(e, t) {
  const s = {
    get(r) {
      const i = this.__v_raw, o = /* @__PURE__ */ re(i), l = /* @__PURE__ */ re(r);
      e || (Xe(r, l) && be(o, "get", r), be(o, "get", l));
      const { has: c } = ms(o), u = t ? yn : e ? Rt : Ve;
      if (c.call(o, r))
        return u(i.get(r));
      if (c.call(o, l))
        return u(i.get(l));
      i !== o && i.get(r);
    },
    get size() {
      const r = this.__v_raw;
      return !e && be(/* @__PURE__ */ re(r), "iterate", Ct), r.size;
    },
    has(r) {
      const i = this.__v_raw, o = /* @__PURE__ */ re(i), l = /* @__PURE__ */ re(r);
      return e || (Xe(r, l) && be(o, "has", r), be(o, "has", l)), r === l ? i.has(r) : i.has(r) || i.has(l);
    },
    forEach(r, i) {
      const o = this, l = o.__v_raw, c = /* @__PURE__ */ re(l), u = t ? yn : e ? Rt : Ve;
      return !e && be(c, "iterate", Ct), l.forEach((a, d) => r.call(i, u(a), u(d), o));
    }
  };
  return ve(
    s,
    e ? {
      add: vs("add"),
      set: vs("set"),
      delete: vs("delete"),
      clear: vs("clear")
    } : {
      add(r) {
        const i = /* @__PURE__ */ re(this), o = ms(i), l = /* @__PURE__ */ re(r), c = !t && !/* @__PURE__ */ De(r) && !/* @__PURE__ */ ut(r) ? l : r;
        return o.has.call(i, c) || Xe(r, c) && o.has.call(i, r) || Xe(l, c) && o.has.call(i, l) || (i.add(c), ot(i, "add", c, c)), this;
      },
      set(r, i) {
        !t && !/* @__PURE__ */ De(i) && !/* @__PURE__ */ ut(i) && (i = /* @__PURE__ */ re(i));
        const o = /* @__PURE__ */ re(this), { has: l, get: c } = ms(o);
        let u = l.call(o, r);
        u || (r = /* @__PURE__ */ re(r), u = l.call(o, r));
        const a = c.call(o, r);
        return o.set(r, i), u ? Xe(i, a) && ot(o, "set", r, i) : ot(o, "add", r, i), this;
      },
      delete(r) {
        const i = /* @__PURE__ */ re(this), { has: o, get: l } = ms(i);
        let c = o.call(i, r);
        c || (r = /* @__PURE__ */ re(r), c = o.call(i, r)), l && l.call(i, r);
        const u = i.delete(r);
        return c && ot(i, "delete", r, void 0), u;
      },
      clear() {
        const r = /* @__PURE__ */ re(this), i = r.size !== 0, o = r.clear();
        return i && ot(
          r,
          "clear",
          void 0,
          void 0
        ), o;
      }
    }
  ), [
    "keys",
    "values",
    "entries",
    Symbol.iterator
  ].forEach((r) => {
    s[r] = jo(r, e, t);
  }), s;
}
function Un(e, t) {
  const s = No(e, t);
  return (n, r, i) => r === "__v_isReactive" ? !e : r === "__v_isReadonly" ? e : r === "__v_raw" ? n : Reflect.get(
    ie(s, r) && r in n ? s : n,
    r,
    i
  );
}
const Vo = {
  get: /* @__PURE__ */ Un(!1, !1)
}, Wo = {
  get: /* @__PURE__ */ Un(!1, !0)
}, Bo = {
  get: /* @__PURE__ */ Un(!0, !1)
};
const _i = /* @__PURE__ */ new WeakMap(), gi = /* @__PURE__ */ new WeakMap(), mi = /* @__PURE__ */ new WeakMap(), Uo = /* @__PURE__ */ new WeakMap();
function Ko(e) {
  switch (e) {
    case "Object":
    case "Array":
      return 1;
    case "Map":
    case "Set":
    case "WeakMap":
    case "WeakSet":
      return 2;
    default:
      return 0;
  }
}
function Go(e) {
  return e.__v_skip || !Object.isExtensible(e) ? 0 : Ko(go(e));
}
// @__NO_SIDE_EFFECTS__
function Us(e) {
  return /* @__PURE__ */ ut(e) ? e : Kn(
    e,
    !1,
    Fo,
    Vo,
    _i
  );
}
// @__NO_SIDE_EFFECTS__
function qo(e) {
  return Kn(
    e,
    !1,
    Ro,
    Wo,
    gi
  );
}
// @__NO_SIDE_EFFECTS__
function xn(e) {
  return Kn(
    e,
    !0,
    Do,
    Bo,
    mi
  );
}
function Kn(e, t, s, n, r) {
  if (!oe(e) || e.__v_raw && !(t && e.__v_isReactive))
    return e;
  const i = Go(e);
  if (i === 0)
    return e;
  const o = r.get(e);
  if (o)
    return o;
  const l = new Proxy(
    e,
    i === 2 ? n : s
  );
  return r.set(e, l), l;
}
// @__NO_SIDE_EFFECTS__
function $t(e) {
  return /* @__PURE__ */ ut(e) ? /* @__PURE__ */ $t(e.__v_raw) : !!(e && e.__v_isReactive);
}
// @__NO_SIDE_EFFECTS__
function ut(e) {
  return !!(e && e.__v_isReadonly);
}
// @__NO_SIDE_EFFECTS__
function De(e) {
  return !!(e && e.__v_isShallow);
}
// @__NO_SIDE_EFFECTS__
function Gn(e) {
  return e ? !!e.__v_raw : !1;
}
// @__NO_SIDE_EFFECTS__
function re(e) {
  const t = e && e.__v_raw;
  return t ? /* @__PURE__ */ re(t) : e;
}
function Jo(e) {
  return !ie(e, "__v_skip") && Object.isExtensible(e) && Xr(e, "__v_skip", !0), e;
}
const Ve = (e) => oe(e) ? /* @__PURE__ */ Us(e) : e, Rt = (e) => oe(e) ? /* @__PURE__ */ xn(e) : e;
// @__NO_SIDE_EFFECTS__
function we(e) {
  return e ? e.__v_isRef === !0 : !1;
}
// @__NO_SIDE_EFFECTS__
function He(e) {
  return zo(e, !1);
}
function zo(e, t) {
  return /* @__PURE__ */ we(e) ? e : new Zo(e, t);
}
class Zo {
  constructor(t, s) {
    this.dep = new Bn(), this.__v_isRef = !0, this.__v_isShallow = !1, this._rawValue = s ? t : /* @__PURE__ */ re(t), this._value = s ? t : Ve(t), this.__v_isShallow = s;
  }
  get value() {
    return this.dep.track(), this._value;
  }
  set value(t) {
    const s = this._rawValue, n = this.__v_isShallow || /* @__PURE__ */ De(t) || /* @__PURE__ */ ut(t);
    t = n ? t : /* @__PURE__ */ re(t), Xe(t, s) && (this._rawValue = t, this._value = n ? t : Ve(t), this.dep.trigger());
  }
}
function g(e) {
  return /* @__PURE__ */ we(e) ? e.value : e;
}
const Yo = {
  get: (e, t, s) => t === "__v_raw" ? e : g(Reflect.get(e, t, s)),
  set: (e, t, s, n) => {
    const r = e[t];
    return /* @__PURE__ */ we(r) && !/* @__PURE__ */ we(s) ? (r.value = s, !0) : Reflect.set(e, t, s, n);
  }
};
function vi(e) {
  return /* @__PURE__ */ $t(e) ? e : new Proxy(e, Yo);
}
class Qo {
  constructor(t, s, n) {
    this.fn = t, this.setter = s, this._value = void 0, this.dep = new Bn(this), this.__v_isRef = !0, this.deps = void 0, this.depsTail = void 0, this.flags = 16, this.globalVersion = ss - 1, this.next = void 0, this.effect = this, this.__v_isReadonly = !s, this.isSSR = n;
  }
  /**
   * @internal
   */
  notify() {
    if (this.flags |= 16, !(this.flags & 8) && // avoid infinite self recursion
    de !== this)
      return oi(this, !0), !0;
  }
  get value() {
    const t = this.dep.track();
    return ai(this), t && (t.version = this.dep.version), this._value;
  }
  set value(t) {
    this.setter && this.setter(t);
  }
}
// @__NO_SIDE_EFFECTS__
function Xo(e, t, s = !1) {
  let n, r;
  return te(e) ? n = e : (n = e.get, r = e.set), new Qo(n, r, s);
}
const ys = {}, Ss = /* @__PURE__ */ new WeakMap();
let bt;
function el(e, t = !1, s = bt) {
  if (s) {
    let n = Ss.get(s);
    n || Ss.set(s, n = []), n.push(e);
  }
}
function tl(e, t, s = ae) {
  const { immediate: n, deep: r, once: i, scheduler: o, augmentJob: l, call: c } = s, u = (x) => r ? x : /* @__PURE__ */ De(x) || r === !1 || r === 0 ? lt(x, 1) : lt(x);
  let a, d, y, $, H = !1, O = !1;
  if (/* @__PURE__ */ we(e) ? (d = () => e.value, H = /* @__PURE__ */ De(e)) : /* @__PURE__ */ $t(e) ? (d = () => u(e), H = !0) : G(e) ? (O = !0, H = e.some((x) => /* @__PURE__ */ $t(x) || /* @__PURE__ */ De(x)), d = () => e.map((x) => {
    if (/* @__PURE__ */ we(x))
      return x.value;
    if (/* @__PURE__ */ $t(x))
      return u(x);
    if (te(x))
      return c ? c(x, 2) : x();
  })) : te(e) ? t ? d = c ? () => c(e, 2) : e : d = () => {
    if (y) {
      ct();
      try {
        y();
      } finally {
        at();
      }
    }
    const x = bt;
    bt = a;
    try {
      return c ? c(e, 3, [$]) : e($);
    } finally {
      bt = x;
    }
  } : d = et, t && r) {
    const x = d, V = r === !0 ? 1 / 0 : r;
    d = () => lt(x(), V);
  }
  const Y = Mo(), B = () => {
    a.stop(), Y && Y.active && Fn(Y.effects, a);
  };
  if (i && t) {
    const x = t;
    t = (...V) => {
      x(...V), B();
    };
  }
  let E = O ? new Array(e.length).fill(ys) : ys;
  const z = (x) => {
    if (!(!(a.flags & 1) || !a.dirty && !x))
      if (t) {
        const V = a.run();
        if (r || H || (O ? V.some((X, ee) => Xe(X, E[ee])) : Xe(V, E))) {
          y && y();
          const X = bt;
          bt = a;
          try {
            const ee = [
              V,
              // pass undefined as the old value when it's changed for the first time
              E === ys ? void 0 : O && E[0] === ys ? [] : E,
              $
            ];
            E = V, c ? c(t, 3, ee) : (
              // @ts-expect-error
              t(...ee)
            );
          } finally {
            bt = X;
          }
        }
      } else
        a.run();
  };
  return l && l(z), a = new ri(d), a.scheduler = o ? () => o(z, !1) : z, $ = (x) => el(x, !1, a), y = a.onStop = () => {
    const x = Ss.get(a);
    if (x) {
      if (c)
        c(x, 4);
      else
        for (const V of x) V();
      Ss.delete(a);
    }
  }, t ? n ? z(!0) : E = a.run() : o ? o(z.bind(null, !0), !0) : a.run(), B.pause = a.pause.bind(a), B.resume = a.resume.bind(a), B.stop = B, B;
}
function lt(e, t = 1 / 0, s) {
  if (t <= 0 || !oe(e) || e.__v_skip || (s = s || /* @__PURE__ */ new Map(), (s.get(e) || 0) >= t))
    return e;
  if (s.set(e, t), t--, /* @__PURE__ */ we(e))
    lt(e.value, t, s);
  else if (G(e))
    for (let n = 0; n < e.length; n++)
      lt(e[n], t, s);
  else if (Ns(e) || Ot(e))
    e.forEach((n) => {
      lt(n, t, s);
    });
  else if (Yr(e)) {
    for (const n in e)
      lt(e[n], t, s);
    for (const n of Object.getOwnPropertySymbols(e))
      Object.prototype.propertyIsEnumerable.call(e, n) && lt(e[n], t, s);
  }
  return e;
}
/**
* @vue/runtime-core v3.5.30
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
function fs(e, t, s, n) {
  try {
    return n ? e(...n) : e();
  } catch (r) {
    Ks(r, t, s);
  }
}
function We(e, t, s, n) {
  if (te(e)) {
    const r = fs(e, t, s, n);
    return r && zr(r) && r.catch((i) => {
      Ks(i, t, s);
    }), r;
  }
  if (G(e)) {
    const r = [];
    for (let i = 0; i < e.length; i++)
      r.push(We(e[i], t, s, n));
    return r;
  }
}
function Ks(e, t, s, n = !0) {
  const r = t ? t.vnode : null, { errorHandler: i, throwUnhandledErrorInProduction: o } = t && t.appContext.config || ae;
  if (t) {
    let l = t.parent;
    const c = t.proxy, u = `https://vuejs.org/error-reference/#runtime-${s}`;
    for (; l; ) {
      const a = l.ec;
      if (a) {
        for (let d = 0; d < a.length; d++)
          if (a[d](e, c, u) === !1)
            return;
      }
      l = l.parent;
    }
    if (i) {
      ct(), fs(i, null, 10, [
        e,
        c,
        u
      ]), at();
      return;
    }
  }
  sl(e, s, r, n, o);
}
function sl(e, t, s, n = !0, r = !1) {
  if (r)
    throw e;
  console.error(e);
}
const Ce = [];
let ze = -1;
const Ht = [];
let pt = null, Et = 0;
const yi = /* @__PURE__ */ Promise.resolve();
let Ms = null;
function nl(e) {
  const t = Ms || yi;
  return e ? t.then(this ? e.bind(this) : e) : t;
}
function rl(e) {
  let t = ze + 1, s = Ce.length;
  for (; t < s; ) {
    const n = t + s >>> 1, r = Ce[n], i = rs(r);
    i < e || i === e && r.flags & 2 ? t = n + 1 : s = n;
  }
  return t;
}
function qn(e) {
  if (!(e.flags & 1)) {
    const t = rs(e), s = Ce[Ce.length - 1];
    !s || // fast path when the job id is larger than the tail
    !(e.flags & 2) && t >= rs(s) ? Ce.push(e) : Ce.splice(rl(t), 0, e), e.flags |= 1, xi();
  }
}
function xi() {
  Ms || (Ms = yi.then(wi));
}
function il(e) {
  G(e) ? Ht.push(...e) : pt && e.id === -1 ? pt.splice(Et + 1, 0, e) : e.flags & 1 || (Ht.push(e), e.flags |= 1), xi();
}
function cr(e, t, s = ze + 1) {
  for (; s < Ce.length; s++) {
    const n = Ce[s];
    if (n && n.flags & 2) {
      if (e && n.id !== e.uid)
        continue;
      Ce.splice(s, 1), s--, n.flags & 4 && (n.flags &= -2), n(), n.flags & 4 || (n.flags &= -2);
    }
  }
}
function bi(e) {
  if (Ht.length) {
    const t = [...new Set(Ht)].sort(
      (s, n) => rs(s) - rs(n)
    );
    if (Ht.length = 0, pt) {
      pt.push(...t);
      return;
    }
    for (pt = t, Et = 0; Et < pt.length; Et++) {
      const s = pt[Et];
      s.flags & 4 && (s.flags &= -2), s.flags & 8 || s(), s.flags &= -2;
    }
    pt = null, Et = 0;
  }
}
const rs = (e) => e.id == null ? e.flags & 2 ? -1 : 1 / 0 : e.id;
function wi(e) {
  try {
    for (ze = 0; ze < Ce.length; ze++) {
      const t = Ce[ze];
      t && !(t.flags & 8) && (t.flags & 4 && (t.flags &= -2), fs(
        t,
        t.i,
        t.i ? 15 : 14
      ), t.flags & 4 || (t.flags &= -2));
    }
  } finally {
    for (; ze < Ce.length; ze++) {
      const t = Ce[ze];
      t && (t.flags &= -2);
    }
    ze = -1, Ce.length = 0, bi(), Ms = null, (Ce.length || Ht.length) && wi();
  }
}
let Fe = null, ki = null;
function Ps(e) {
  const t = Fe;
  return Fe = e, ki = e && e.type.__scopeId || null, t;
}
function ds(e, t = Fe, s) {
  if (!t || e._n)
    return e;
  const n = (...r) => {
    n._d && Es(-1);
    const i = Ps(t);
    let o;
    try {
      o = e(...r);
    } finally {
      Ps(i), n._d && Es(1);
    }
    return o;
  };
  return n._n = !0, n._c = !0, n._d = !0, n;
}
function Gt(e, t) {
  if (Fe === null)
    return e;
  const s = Qs(Fe), n = e.dirs || (e.dirs = []);
  for (let r = 0; r < t.length; r++) {
    let [i, o, l, c = ae] = t[r];
    i && (te(i) && (i = {
      mounted: i,
      updated: i
    }), i.deep && lt(o), n.push({
      dir: i,
      instance: s,
      value: o,
      oldValue: void 0,
      arg: l,
      modifiers: c
    }));
  }
  return e;
}
function mt(e, t, s, n) {
  const r = e.dirs, i = t && t.dirs;
  for (let o = 0; o < r.length; o++) {
    const l = r[o];
    i && (l.oldValue = i[o].value);
    let c = l.dir[n];
    c && (ct(), We(c, s, 8, [
      e.el,
      l,
      e,
      t
    ]), at());
  }
}
function ol(e, t) {
  if (Te) {
    let s = Te.provides;
    const n = Te.parent && Te.parent.provides;
    n === s && (s = Te.provides = Object.create(n)), s[e] = t;
  }
}
function ks(e, t, s = !1) {
  const n = to();
  if (n || Ft) {
    let r = Ft ? Ft._context.provides : n ? n.parent == null || n.ce ? n.vnode.appContext && n.vnode.appContext.provides : n.parent.provides : void 0;
    if (r && e in r)
      return r[e];
    if (arguments.length > 1)
      return s && te(t) ? t.call(n && n.proxy) : t;
  }
}
const ll = /* @__PURE__ */ Symbol.for("v-scx"), cl = () => ks(ll);
function Ae(e, t, s) {
  return Ci(e, t, s);
}
function Ci(e, t, s = ae) {
  const { immediate: n, deep: r, flush: i, once: o } = s, l = ve({}, s), c = t && n || !t && i !== "post";
  let u;
  if (ls) {
    if (i === "sync") {
      const $ = cl();
      u = $.__watcherHandles || ($.__watcherHandles = []);
    } else if (!c) {
      const $ = () => {
      };
      return $.stop = et, $.resume = et, $.pause = et, $;
    }
  }
  const a = Te;
  l.call = ($, H, O) => We($, a, H, O);
  let d = !1;
  i === "post" ? l.scheduler = ($) => {
    xe($, a && a.suspense);
  } : i !== "sync" && (d = !0, l.scheduler = ($, H) => {
    H ? $() : qn($);
  }), l.augmentJob = ($) => {
    t && ($.flags |= 4), d && ($.flags |= 2, a && ($.id = a.uid, $.i = a));
  };
  const y = tl(e, t, l);
  return ls && (u ? u.push(y) : c && y()), y;
}
function al(e, t, s) {
  const n = this.proxy, r = _e(e) ? e.includes(".") ? $i(n, e) : () => n[e] : e.bind(n, n);
  let i;
  te(t) ? i = t : (i = t.handler, s = t);
  const o = hs(this), l = Ci(r, i.bind(n), s);
  return o(), l;
}
function $i(e, t) {
  const s = t.split(".");
  return () => {
    let n = e;
    for (let r = 0; r < s.length && n; r++)
      n = n[s[r]];
    return n;
  };
}
const Ti = /* @__PURE__ */ Symbol("_vte"), Si = (e) => e.__isTeleport, Yt = (e) => e && (e.disabled || e.disabled === ""), ar = (e) => e && (e.defer || e.defer === ""), ur = (e) => typeof SVGElement < "u" && e instanceof SVGElement, fr = (e) => typeof MathMLElement == "function" && e instanceof MathMLElement, bn = (e, t) => {
  const s = e && e.to;
  return _e(s) ? t ? t(s) : null : s;
}, Mi = {
  name: "Teleport",
  __isTeleport: !0,
  process(e, t, s, n, r, i, o, l, c, u) {
    const {
      mc: a,
      pc: d,
      pbc: y,
      o: { insert: $, querySelector: H, createText: O, createComment: Y }
    } = u, B = Yt(t.props);
    let { shapeFlag: E, children: z, dynamicChildren: x } = t;
    if (e == null) {
      const V = t.el = O(""), X = t.anchor = O("");
      $(V, s, n), $(X, s, n);
      const ee = (S, j) => {
        E & 16 && a(
          z,
          S,
          j,
          r,
          i,
          o,
          l,
          c
        );
      }, se = () => {
        const S = t.target = bn(t.props, H), j = wn(S, t, O, $);
        S && (o !== "svg" && ur(S) ? o = "svg" : o !== "mathml" && fr(S) && (o = "mathml"), r && r.isCE && (r.ce._teleportTargets || (r.ce._teleportTargets = /* @__PURE__ */ new Set())).add(S), B || (ee(S, j), Cs(t, !1)));
      };
      B && (ee(s, X), Cs(t, !0)), ar(t.props) ? (t.el.__isMounted = !1, xe(() => {
        se(), delete t.el.__isMounted;
      }, i)) : se();
    } else {
      if (ar(t.props) && e.el.__isMounted === !1) {
        xe(() => {
          Mi.process(
            e,
            t,
            s,
            n,
            r,
            i,
            o,
            l,
            c,
            u
          );
        }, i);
        return;
      }
      t.el = e.el, t.targetStart = e.targetStart;
      const V = t.anchor = e.anchor, X = t.target = e.target, ee = t.targetAnchor = e.targetAnchor, se = Yt(e.props), S = se ? s : X, j = se ? V : ee;
      if (o === "svg" || ur(X) ? o = "svg" : (o === "mathml" || fr(X)) && (o = "mathml"), x ? (y(
        e.dynamicChildren,
        x,
        S,
        r,
        i,
        o,
        l
      ), Zn(e, t, !0)) : c || d(
        e,
        t,
        S,
        j,
        r,
        i,
        o,
        l,
        !1
      ), B)
        se ? t.props && e.props && t.props.to !== e.props.to && (t.props.to = e.props.to) : xs(
          t,
          s,
          V,
          u,
          1
        );
      else if ((t.props && t.props.to) !== (e.props && e.props.to)) {
        const q = t.target = bn(
          t.props,
          H
        );
        q && xs(
          t,
          q,
          null,
          u,
          0
        );
      } else se && xs(
        t,
        X,
        ee,
        u,
        1
      );
      Cs(t, B);
    }
  },
  remove(e, t, s, { um: n, o: { remove: r } }, i) {
    const {
      shapeFlag: o,
      children: l,
      anchor: c,
      targetStart: u,
      targetAnchor: a,
      target: d,
      props: y
    } = e;
    if (d && (r(u), r(a)), i && r(c), o & 16) {
      const $ = i || !Yt(y);
      for (let H = 0; H < l.length; H++) {
        const O = l[H];
        n(
          O,
          t,
          s,
          $,
          !!O.dynamicChildren
        );
      }
    }
  },
  move: xs,
  hydrate: ul
};
function xs(e, t, s, { o: { insert: n }, m: r }, i = 2) {
  i === 0 && n(e.targetAnchor, t, s);
  const { el: o, anchor: l, shapeFlag: c, children: u, props: a } = e, d = i === 2;
  if (d && n(o, t, s), (!d || Yt(a)) && c & 16)
    for (let y = 0; y < u.length; y++)
      r(
        u[y],
        t,
        s,
        2
      );
  d && n(l, t, s);
}
function ul(e, t, s, n, r, i, {
  o: { nextSibling: o, parentNode: l, querySelector: c, insert: u, createText: a }
}, d) {
  function y(Y, B) {
    let E = B;
    for (; E; ) {
      if (E && E.nodeType === 8) {
        if (E.data === "teleport start anchor")
          t.targetStart = E;
        else if (E.data === "teleport anchor") {
          t.targetAnchor = E, Y._lpa = t.targetAnchor && o(t.targetAnchor);
          break;
        }
      }
      E = o(E);
    }
  }
  function $(Y, B) {
    B.anchor = d(
      o(Y),
      B,
      l(Y),
      s,
      n,
      r,
      i
    );
  }
  const H = t.target = bn(
    t.props,
    c
  ), O = Yt(t.props);
  if (H) {
    const Y = H._lpa || H.firstChild;
    t.shapeFlag & 16 && (O ? ($(e, t), y(H, Y), t.targetAnchor || wn(
      H,
      t,
      a,
      u,
      // if target is the same as the main view, insert anchors before current node
      // to avoid hydrating mismatch
      l(e) === H ? e : null
    )) : (t.anchor = o(e), y(H, Y), t.targetAnchor || wn(H, t, a, u), d(
      Y && o(Y),
      t,
      H,
      s,
      n,
      r,
      i
    ))), Cs(t, O);
  } else O && t.shapeFlag & 16 && ($(e, t), t.targetStart = e, t.targetAnchor = o(e));
  return t.anchor && o(t.anchor);
}
const Gs = Mi;
function Cs(e, t) {
  const s = e.ctx;
  if (s && s.ut) {
    let n, r;
    for (t ? (n = e.el, r = e.anchor) : (n = e.targetStart, r = e.targetAnchor); n && n !== r; )
      n.nodeType === 1 && n.setAttribute("data-v-owner", s.uid), n = n.nextSibling;
    s.ut();
  }
}
function wn(e, t, s, n, r = null) {
  const i = t.targetStart = s(""), o = t.targetAnchor = s("");
  return i[Ti] = o, e && (n(i, e, r), n(o, e, r)), o;
}
const Ze = /* @__PURE__ */ Symbol("_leaveCb"), Bt = /* @__PURE__ */ Symbol("_enterCb");
function fl() {
  const e = {
    isMounted: !1,
    isLeaving: !1,
    isUnmounting: !1,
    leavingVNodes: /* @__PURE__ */ new Map()
  };
  return St(() => {
    e.isMounted = !0;
  }), Fi(() => {
    e.isUnmounting = !0;
  }), e;
}
const Oe = [Function, Array], Pi = {
  mode: String,
  appear: Boolean,
  persisted: Boolean,
  // enter
  onBeforeEnter: Oe,
  onEnter: Oe,
  onAfterEnter: Oe,
  onEnterCancelled: Oe,
  // leave
  onBeforeLeave: Oe,
  onLeave: Oe,
  onAfterLeave: Oe,
  onLeaveCancelled: Oe,
  // appear
  onBeforeAppear: Oe,
  onAppear: Oe,
  onAfterAppear: Oe,
  onAppearCancelled: Oe
}, Li = (e) => {
  const t = e.subTree;
  return t.component ? Li(t.component) : t;
}, dl = {
  name: "BaseTransition",
  props: Pi,
  setup(e, { slots: t }) {
    const s = to(), n = fl();
    return () => {
      const r = t.default && Ii(t.default(), !0);
      if (!r || !r.length)
        return;
      const i = Ai(r), o = /* @__PURE__ */ re(e), { mode: l } = o;
      if (n.isLeaving)
        return ln(i);
      const c = dr(i);
      if (!c)
        return ln(i);
      let u = kn(
        c,
        o,
        n,
        s,
        // #11061, ensure enterHooks is fresh after clone
        (d) => u = d
      );
      c.type !== $e && is(c, u);
      let a = s.subTree && dr(s.subTree);
      if (a && a.type !== $e && !wt(a, c) && Li(s).type !== $e) {
        let d = kn(
          a,
          o,
          n,
          s
        );
        if (is(a, d), l === "out-in" && c.type !== $e)
          return n.isLeaving = !0, d.afterLeave = () => {
            n.isLeaving = !1, s.job.flags & 8 || s.update(), delete d.afterLeave, a = void 0;
          }, ln(i);
        l === "in-out" && c.type !== $e ? d.delayLeave = (y, $, H) => {
          const O = Ei(
            n,
            a
          );
          O[String(a.key)] = a, y[Ze] = () => {
            $(), y[Ze] = void 0, delete u.delayedLeave, a = void 0;
          }, u.delayedLeave = () => {
            H(), delete u.delayedLeave, a = void 0;
          };
        } : a = void 0;
      } else a && (a = void 0);
      return i;
    };
  }
};
function Ai(e) {
  let t = e[0];
  if (e.length > 1) {
    for (const s of e)
      if (s.type !== $e) {
        t = s;
        break;
      }
  }
  return t;
}
const pl = dl;
function Ei(e, t) {
  const { leavingVNodes: s } = e;
  let n = s.get(t.type);
  return n || (n = /* @__PURE__ */ Object.create(null), s.set(t.type, n)), n;
}
function kn(e, t, s, n, r) {
  const {
    appear: i,
    mode: o,
    persisted: l = !1,
    onBeforeEnter: c,
    onEnter: u,
    onAfterEnter: a,
    onEnterCancelled: d,
    onBeforeLeave: y,
    onLeave: $,
    onAfterLeave: H,
    onLeaveCancelled: O,
    onBeforeAppear: Y,
    onAppear: B,
    onAfterAppear: E,
    onAppearCancelled: z
  } = t, x = String(e.key), V = Ei(s, e), X = (S, j) => {
    S && We(
      S,
      n,
      9,
      j
    );
  }, ee = (S, j) => {
    const q = j[1];
    X(S, j), G(S) ? S.every((D) => D.length <= 1) && q() : S.length <= 1 && q();
  }, se = {
    mode: o,
    persisted: l,
    beforeEnter(S) {
      let j = c;
      if (!s.isMounted)
        if (i)
          j = Y || c;
        else
          return;
      S[Ze] && S[Ze](
        !0
        /* cancelled */
      );
      const q = V[x];
      q && wt(e, q) && q.el[Ze] && q.el[Ze](), X(j, [S]);
    },
    enter(S) {
      if (V[x] === e) return;
      let j = u, q = a, D = d;
      if (!s.isMounted)
        if (i)
          j = B || u, q = E || a, D = z || d;
        else
          return;
      let M = !1;
      S[Bt] = (b) => {
        M || (M = !0, b ? X(D, [S]) : X(q, [S]), se.delayedLeave && se.delayedLeave(), S[Bt] = void 0);
      };
      const R = S[Bt].bind(null, !1);
      j ? ee(j, [S, R]) : R();
    },
    leave(S, j) {
      const q = String(e.key);
      if (S[Bt] && S[Bt](
        !0
        /* cancelled */
      ), s.isUnmounting)
        return j();
      X(y, [S]);
      let D = !1;
      S[Ze] = (R) => {
        D || (D = !0, j(), R ? X(O, [S]) : X(H, [S]), S[Ze] = void 0, V[q] === e && delete V[q]);
      };
      const M = S[Ze].bind(null, !1);
      V[q] = e, $ ? ee($, [S, M]) : M();
    },
    clone(S) {
      const j = kn(
        S,
        t,
        s,
        n,
        r
      );
      return r && r(j), j;
    }
  };
  return se;
}
function ln(e) {
  if (qs(e))
    return e = ht(e), e.children = null, e;
}
function dr(e) {
  if (!qs(e))
    return Si(e.type) && e.children ? Ai(e.children) : e;
  if (e.component)
    return e.component.subTree;
  const { shapeFlag: t, children: s } = e;
  if (s) {
    if (t & 16)
      return s[0];
    if (t & 32 && te(s.default))
      return s.default();
  }
}
function is(e, t) {
  e.shapeFlag & 6 && e.component ? (e.transition = t, is(e.component.subTree, t)) : e.shapeFlag & 128 ? (e.ssContent.transition = t.clone(e.ssContent), e.ssFallback.transition = t.clone(e.ssFallback)) : e.transition = t;
}
function Ii(e, t = !1, s) {
  let n = [], r = 0;
  for (let i = 0; i < e.length; i++) {
    let o = e[i];
    const l = s == null ? o.key : String(s) + String(o.key != null ? o.key : i);
    o.type === Z ? (o.patchFlag & 128 && r++, n = n.concat(
      Ii(o.children, t, l)
    )) : (t || o.type !== $e) && n.push(l != null ? ht(o, { key: l }) : o);
  }
  if (r > 1)
    for (let i = 0; i < n.length; i++)
      n[i].patchFlag = -2;
  return n;
}
function Oi(e) {
  e.ids = [e.ids[0] + e.ids[2]++ + "-", 0, 0];
}
function pr(e, t) {
  let s;
  return !!((s = Object.getOwnPropertyDescriptor(e, t)) && !s.configurable);
}
const Ls = /* @__PURE__ */ new WeakMap();
function Qt(e, t, s, n, r = !1) {
  if (G(e)) {
    e.forEach(
      (O, Y) => Qt(
        O,
        t && (G(t) ? t[Y] : t),
        s,
        n,
        r
      )
    );
    return;
  }
  if (Xt(n) && !r) {
    n.shapeFlag & 512 && n.type.__asyncResolved && n.component.subTree.component && Qt(e, t, s, n.component.subTree);
    return;
  }
  const i = n.shapeFlag & 4 ? Qs(n.component) : n.el, o = r ? null : i, { i: l, r: c } = e, u = t && t.r, a = l.refs === ae ? l.refs = {} : l.refs, d = l.setupState, y = /* @__PURE__ */ re(d), $ = d === ae ? Jr : (O) => pr(a, O) ? !1 : ie(y, O), H = (O, Y) => !(Y && pr(a, Y));
  if (u != null && u !== c) {
    if (hr(t), _e(u))
      a[u] = null, $(u) && (d[u] = null);
    else if (/* @__PURE__ */ we(u)) {
      const O = t;
      H(u, O.k) && (u.value = null), O.k && (a[O.k] = null);
    }
  }
  if (te(c))
    fs(c, l, 12, [o, a]);
  else {
    const O = _e(c), Y = /* @__PURE__ */ we(c);
    if (O || Y) {
      const B = () => {
        if (e.f) {
          const E = O ? $(c) ? d[c] : a[c] : H() || !e.k ? c.value : a[e.k];
          if (r)
            G(E) && Fn(E, i);
          else if (G(E))
            E.includes(i) || E.push(i);
          else if (O)
            a[c] = [i], $(c) && (d[c] = a[c]);
          else {
            const z = [i];
            H(c, e.k) && (c.value = z), e.k && (a[e.k] = z);
          }
        } else O ? (a[c] = o, $(c) && (d[c] = o)) : Y && (H(c, e.k) && (c.value = o), e.k && (a[e.k] = o));
      };
      if (o) {
        const E = () => {
          B(), Ls.delete(e);
        };
        E.id = -1, Ls.set(e, E), xe(E, s);
      } else
        hr(e), B();
    }
  }
}
function hr(e) {
  const t = Ls.get(e);
  t && (t.flags |= 8, Ls.delete(e));
}
Ws().requestIdleCallback;
Ws().cancelIdleCallback;
const Xt = (e) => !!e.type.__asyncLoader, qs = (e) => e.type.__isKeepAlive;
function hl(e, t) {
  Hi(e, "a", t);
}
function _l(e, t) {
  Hi(e, "da", t);
}
function Hi(e, t, s = Te) {
  const n = e.__wdc || (e.__wdc = () => {
    let r = s;
    for (; r; ) {
      if (r.isDeactivated)
        return;
      r = r.parent;
    }
    return e();
  });
  if (Js(t, n, s), s) {
    let r = s.parent;
    for (; r && r.parent; )
      qs(r.parent.vnode) && gl(n, t, s, r), r = r.parent;
  }
}
function gl(e, t, s, n) {
  const r = Js(
    t,
    e,
    n,
    !0
    /* prepend */
  );
  zs(() => {
    Fn(n[t], r);
  }, s);
}
function Js(e, t, s = Te, n = !1) {
  if (s) {
    const r = s[e] || (s[e] = []), i = t.__weh || (t.__weh = (...o) => {
      ct();
      const l = hs(s), c = We(t, s, e, o);
      return l(), at(), c;
    });
    return n ? r.unshift(i) : r.push(i), i;
  }
}
const ft = (e) => (t, s = Te) => {
  (!ls || e === "sp") && Js(e, (...n) => t(...n), s);
}, ml = ft("bm"), St = ft("m"), vl = ft(
  "bu"
), yl = ft("u"), Fi = ft(
  "bum"
), zs = ft("um"), xl = ft(
  "sp"
), bl = ft("rtg"), wl = ft("rtc");
function kl(e, t = Te) {
  Js("ec", e, t);
}
const Cl = /* @__PURE__ */ Symbol.for("v-ndc");
function ce(e, t, s, n) {
  let r;
  const i = s, o = G(e);
  if (o || _e(e)) {
    const l = o && /* @__PURE__ */ $t(e);
    let c = !1, u = !1;
    l && (c = !/* @__PURE__ */ De(e), u = /* @__PURE__ */ ut(e), e = Bs(e)), r = new Array(e.length);
    for (let a = 0, d = e.length; a < d; a++)
      r[a] = t(
        c ? u ? Rt(Ve(e[a])) : Ve(e[a]) : e[a],
        a,
        void 0,
        i
      );
  } else if (typeof e == "number") {
    r = new Array(e);
    for (let l = 0; l < e; l++)
      r[l] = t(l + 1, l, void 0, i);
  } else if (oe(e))
    if (e[Symbol.iterator])
      r = Array.from(
        e,
        (l, c) => t(l, c, void 0, i)
      );
    else {
      const l = Object.keys(e);
      r = new Array(l.length);
      for (let c = 0, u = l.length; c < u; c++) {
        const a = l[c];
        r[c] = t(e[a], a, c, i);
      }
    }
  else
    r = [];
  return r;
}
const Cn = (e) => e ? so(e) ? Qs(e) : Cn(e.parent) : null, es = (
  // Move PURE marker to new line to workaround compiler discarding it
  // due to type annotation
  /* @__PURE__ */ ve(/* @__PURE__ */ Object.create(null), {
    $: (e) => e,
    $el: (e) => e.vnode.el,
    $data: (e) => e.data,
    $props: (e) => e.props,
    $attrs: (e) => e.attrs,
    $slots: (e) => e.slots,
    $refs: (e) => e.refs,
    $parent: (e) => Cn(e.parent),
    $root: (e) => Cn(e.root),
    $host: (e) => e.ce,
    $emit: (e) => e.emit,
    $options: (e) => Ri(e),
    $forceUpdate: (e) => e.f || (e.f = () => {
      qn(e.update);
    }),
    $nextTick: (e) => e.n || (e.n = nl.bind(e.proxy)),
    $watch: (e) => al.bind(e)
  })
), cn = (e, t) => e !== ae && !e.__isScriptSetup && ie(e, t), $l = {
  get({ _: e }, t) {
    if (t === "__v_skip")
      return !0;
    const { ctx: s, setupState: n, data: r, props: i, accessCache: o, type: l, appContext: c } = e;
    if (t[0] !== "$") {
      const y = o[t];
      if (y !== void 0)
        switch (y) {
          case 1:
            return n[t];
          case 2:
            return r[t];
          case 4:
            return s[t];
          case 3:
            return i[t];
        }
      else {
        if (cn(n, t))
          return o[t] = 1, n[t];
        if (r !== ae && ie(r, t))
          return o[t] = 2, r[t];
        if (ie(i, t))
          return o[t] = 3, i[t];
        if (s !== ae && ie(s, t))
          return o[t] = 4, s[t];
        $n && (o[t] = 0);
      }
    }
    const u = es[t];
    let a, d;
    if (u)
      return t === "$attrs" && be(e.attrs, "get", ""), u(e);
    if (
      // css module (injected by vue-loader)
      (a = l.__cssModules) && (a = a[t])
    )
      return a;
    if (s !== ae && ie(s, t))
      return o[t] = 4, s[t];
    if (
      // global properties
      d = c.config.globalProperties, ie(d, t)
    )
      return d[t];
  },
  set({ _: e }, t, s) {
    const { data: n, setupState: r, ctx: i } = e;
    return cn(r, t) ? (r[t] = s, !0) : n !== ae && ie(n, t) ? (n[t] = s, !0) : ie(e.props, t) || t[0] === "$" && t.slice(1) in e ? !1 : (i[t] = s, !0);
  },
  has({
    _: { data: e, setupState: t, accessCache: s, ctx: n, appContext: r, props: i, type: o }
  }, l) {
    let c;
    return !!(s[l] || e !== ae && l[0] !== "$" && ie(e, l) || cn(t, l) || ie(i, l) || ie(n, l) || ie(es, l) || ie(r.config.globalProperties, l) || (c = o.__cssModules) && c[l]);
  },
  defineProperty(e, t, s) {
    return s.get != null ? e._.accessCache[t] = 0 : ie(s, "value") && this.set(e, t, s.value, null), Reflect.defineProperty(e, t, s);
  }
};
function _r(e) {
  return G(e) ? e.reduce(
    (t, s) => (t[s] = null, t),
    {}
  ) : e;
}
let $n = !0;
function Tl(e) {
  const t = Ri(e), s = e.proxy, n = e.ctx;
  $n = !1, t.beforeCreate && gr(t.beforeCreate, e, "bc");
  const {
    // state
    data: r,
    computed: i,
    methods: o,
    watch: l,
    provide: c,
    inject: u,
    // lifecycle
    created: a,
    beforeMount: d,
    mounted: y,
    beforeUpdate: $,
    updated: H,
    activated: O,
    deactivated: Y,
    beforeDestroy: B,
    beforeUnmount: E,
    destroyed: z,
    unmounted: x,
    render: V,
    renderTracked: X,
    renderTriggered: ee,
    errorCaptured: se,
    serverPrefetch: S,
    // public API
    expose: j,
    inheritAttrs: q,
    // assets
    components: D,
    directives: M,
    filters: R
  } = t;
  if (u && Sl(u, n, null), o)
    for (const pe in o) {
      const ue = o[pe];
      te(ue) && (n[pe] = ue.bind(s));
    }
  if (r) {
    const pe = r.call(s, s);
    oe(pe) && (e.data = /* @__PURE__ */ Us(pe));
  }
  if ($n = !0, i)
    for (const pe in i) {
      const ue = i[pe], _t = te(ue) ? ue.bind(s, s) : te(ue.get) ? ue.get.bind(s, s) : et, _s = !te(ue) && te(ue.set) ? ue.set.bind(s) : et, gt = ge({
        get: _t,
        set: _s
      });
      Object.defineProperty(n, pe, {
        enumerable: !0,
        configurable: !0,
        get: () => gt.value,
        set: (Be) => gt.value = Be
      });
    }
  if (l)
    for (const pe in l)
      Di(l[pe], n, s, pe);
  if (c) {
    const pe = te(c) ? c.call(s) : c;
    Reflect.ownKeys(pe).forEach((ue) => {
      ol(ue, pe[ue]);
    });
  }
  a && gr(a, e, "c");
  function W(pe, ue) {
    G(ue) ? ue.forEach((_t) => pe(_t.bind(s))) : ue && pe(ue.bind(s));
  }
  if (W(ml, d), W(St, y), W(vl, $), W(yl, H), W(hl, O), W(_l, Y), W(kl, se), W(wl, X), W(bl, ee), W(Fi, E), W(zs, x), W(xl, S), G(j))
    if (j.length) {
      const pe = e.exposed || (e.exposed = {});
      j.forEach((ue) => {
        Object.defineProperty(pe, ue, {
          get: () => s[ue],
          set: (_t) => s[ue] = _t,
          enumerable: !0
        });
      });
    } else e.exposed || (e.exposed = {});
  V && e.render === et && (e.render = V), q != null && (e.inheritAttrs = q), D && (e.components = D), M && (e.directives = M), S && Oi(e);
}
function Sl(e, t, s = et) {
  G(e) && (e = Tn(e));
  for (const n in e) {
    const r = e[n];
    let i;
    oe(r) ? "default" in r ? i = ks(
      r.from || n,
      r.default,
      !0
    ) : i = ks(r.from || n) : i = ks(r), /* @__PURE__ */ we(i) ? Object.defineProperty(t, n, {
      enumerable: !0,
      configurable: !0,
      get: () => i.value,
      set: (o) => i.value = o
    }) : t[n] = i;
  }
}
function gr(e, t, s) {
  We(
    G(e) ? e.map((n) => n.bind(t.proxy)) : e.bind(t.proxy),
    t,
    s
  );
}
function Di(e, t, s, n) {
  let r = n.includes(".") ? $i(s, n) : () => s[n];
  if (_e(e)) {
    const i = t[e];
    te(i) && Ae(r, i);
  } else if (te(e))
    Ae(r, e.bind(s));
  else if (oe(e))
    if (G(e))
      e.forEach((i) => Di(i, t, s, n));
    else {
      const i = te(e.handler) ? e.handler.bind(s) : t[e.handler];
      te(i) && Ae(r, i, e);
    }
}
function Ri(e) {
  const t = e.type, { mixins: s, extends: n } = t, {
    mixins: r,
    optionsCache: i,
    config: { optionMergeStrategies: o }
  } = e.appContext, l = i.get(t);
  let c;
  return l ? c = l : !r.length && !s && !n ? c = t : (c = {}, r.length && r.forEach(
    (u) => As(c, u, o, !0)
  ), As(c, t, o)), oe(t) && i.set(t, c), c;
}
function As(e, t, s, n = !1) {
  const { mixins: r, extends: i } = t;
  i && As(e, i, s, !0), r && r.forEach(
    (o) => As(e, o, s, !0)
  );
  for (const o in t)
    if (!(n && o === "expose")) {
      const l = Ml[o] || s && s[o];
      e[o] = l ? l(e[o], t[o]) : t[o];
    }
  return e;
}
const Ml = {
  data: mr,
  props: vr,
  emits: vr,
  // objects
  methods: qt,
  computed: qt,
  // lifecycle
  beforeCreate: ke,
  created: ke,
  beforeMount: ke,
  mounted: ke,
  beforeUpdate: ke,
  updated: ke,
  beforeDestroy: ke,
  beforeUnmount: ke,
  destroyed: ke,
  unmounted: ke,
  activated: ke,
  deactivated: ke,
  errorCaptured: ke,
  serverPrefetch: ke,
  // assets
  components: qt,
  directives: qt,
  // watch
  watch: Ll,
  // provide / inject
  provide: mr,
  inject: Pl
};
function mr(e, t) {
  return t ? e ? function() {
    return ve(
      te(e) ? e.call(this, this) : e,
      te(t) ? t.call(this, this) : t
    );
  } : t : e;
}
function Pl(e, t) {
  return qt(Tn(e), Tn(t));
}
function Tn(e) {
  if (G(e)) {
    const t = {};
    for (let s = 0; s < e.length; s++)
      t[e[s]] = e[s];
    return t;
  }
  return e;
}
function ke(e, t) {
  return e ? [...new Set([].concat(e, t))] : t;
}
function qt(e, t) {
  return e ? ve(/* @__PURE__ */ Object.create(null), e, t) : t;
}
function vr(e, t) {
  return e ? G(e) && G(t) ? [.../* @__PURE__ */ new Set([...e, ...t])] : ve(
    /* @__PURE__ */ Object.create(null),
    _r(e),
    _r(t ?? {})
  ) : t;
}
function Ll(e, t) {
  if (!e) return t;
  if (!t) return e;
  const s = ve(/* @__PURE__ */ Object.create(null), e);
  for (const n in t)
    s[n] = ke(e[n], t[n]);
  return s;
}
function ji() {
  return {
    app: null,
    config: {
      isNativeTag: Jr,
      performance: !1,
      globalProperties: {},
      optionMergeStrategies: {},
      errorHandler: void 0,
      warnHandler: void 0,
      compilerOptions: {}
    },
    mixins: [],
    components: {},
    directives: {},
    provides: /* @__PURE__ */ Object.create(null),
    optionsCache: /* @__PURE__ */ new WeakMap(),
    propsCache: /* @__PURE__ */ new WeakMap(),
    emitsCache: /* @__PURE__ */ new WeakMap()
  };
}
let Al = 0;
function El(e, t) {
  return function(n, r = null) {
    te(n) || (n = ve({}, n)), r != null && !oe(r) && (r = null);
    const i = ji(), o = /* @__PURE__ */ new WeakSet(), l = [];
    let c = !1;
    const u = i.app = {
      _uid: Al++,
      _component: n,
      _props: r,
      _container: null,
      _context: i,
      _instance: null,
      version: ac,
      get config() {
        return i.config;
      },
      set config(a) {
      },
      use(a, ...d) {
        return o.has(a) || (a && te(a.install) ? (o.add(a), a.install(u, ...d)) : te(a) && (o.add(a), a(u, ...d))), u;
      },
      mixin(a) {
        return i.mixins.includes(a) || i.mixins.push(a), u;
      },
      component(a, d) {
        return d ? (i.components[a] = d, u) : i.components[a];
      },
      directive(a, d) {
        return d ? (i.directives[a] = d, u) : i.directives[a];
      },
      mount(a, d, y) {
        if (!c) {
          const $ = u._ceVNode || ye(n, r);
          return $.appContext = i, y === !0 ? y = "svg" : y === !1 && (y = void 0), e($, a, y), c = !0, u._container = a, a.__vue_app__ = u, Qs($.component);
        }
      },
      onUnmount(a) {
        l.push(a);
      },
      unmount() {
        c && (We(
          l,
          u._instance,
          16
        ), e(null, u._container), delete u._container.__vue_app__);
      },
      provide(a, d) {
        return i.provides[a] = d, u;
      },
      runWithContext(a) {
        const d = Ft;
        Ft = u;
        try {
          return a();
        } finally {
          Ft = d;
        }
      }
    };
    return u;
  };
}
let Ft = null;
const Il = (e, t) => t === "modelValue" || t === "model-value" ? e.modelModifiers : e[`${t}Modifiers`] || e[`${je(t)}Modifiers`] || e[`${Tt(t)}Modifiers`];
function Ol(e, t, ...s) {
  if (e.isUnmounted) return;
  const n = e.vnode.props || ae;
  let r = s;
  const i = t.startsWith("update:"), o = i && Il(n, t.slice(7));
  o && (o.trim && (r = s.map((a) => _e(a) ? a.trim() : a)), o.number && (r = s.map(Rn)));
  let l, c = n[l = tn(t)] || // also try camelCase event handler (#2249)
  n[l = tn(je(t))];
  !c && i && (c = n[l = tn(Tt(t))]), c && We(
    c,
    e,
    6,
    r
  );
  const u = n[l + "Once"];
  if (u) {
    if (!e.emitted)
      e.emitted = {};
    else if (e.emitted[l])
      return;
    e.emitted[l] = !0, We(
      u,
      e,
      6,
      r
    );
  }
}
const Hl = /* @__PURE__ */ new WeakMap();
function Ni(e, t, s = !1) {
  const n = s ? Hl : t.emitsCache, r = n.get(e);
  if (r !== void 0)
    return r;
  const i = e.emits;
  let o = {}, l = !1;
  if (!te(e)) {
    const c = (u) => {
      const a = Ni(u, t, !0);
      a && (l = !0, ve(o, a));
    };
    !s && t.mixins.length && t.mixins.forEach(c), e.extends && c(e.extends), e.mixins && e.mixins.forEach(c);
  }
  return !i && !l ? (oe(e) && n.set(e, null), null) : (G(i) ? i.forEach((c) => o[c] = null) : ve(o, i), oe(e) && n.set(e, o), o);
}
function Zs(e, t) {
  return !e || !js(t) ? !1 : (t = t.slice(2).replace(/Once$/, ""), ie(e, t[0].toLowerCase() + t.slice(1)) || ie(e, Tt(t)) || ie(e, t));
}
function yr(e) {
  const {
    type: t,
    vnode: s,
    proxy: n,
    withProxy: r,
    propsOptions: [i],
    slots: o,
    attrs: l,
    emit: c,
    render: u,
    renderCache: a,
    props: d,
    data: y,
    setupState: $,
    ctx: H,
    inheritAttrs: O
  } = e, Y = Ps(e);
  let B, E;
  try {
    if (s.shapeFlag & 4) {
      const x = r || n, V = x;
      B = Qe(
        u.call(
          V,
          x,
          a,
          d,
          $,
          y,
          H
        )
      ), E = l;
    } else {
      const x = t;
      B = Qe(
        x.length > 1 ? x(
          d,
          { attrs: l, slots: o, emit: c }
        ) : x(
          d,
          null
        )
      ), E = t.props ? l : Fl(l);
    }
  } catch (x) {
    ts.length = 0, Ks(x, e, 1), B = ye($e);
  }
  let z = B;
  if (E && O !== !1) {
    const x = Object.keys(E), { shapeFlag: V } = z;
    x.length && V & 7 && (i && x.some(Hn) && (E = Dl(
      E,
      i
    )), z = ht(z, E, !1, !0));
  }
  return s.dirs && (z = ht(z, null, !1, !0), z.dirs = z.dirs ? z.dirs.concat(s.dirs) : s.dirs), s.transition && is(z, s.transition), B = z, Ps(Y), B;
}
const Fl = (e) => {
  let t;
  for (const s in e)
    (s === "class" || s === "style" || js(s)) && ((t || (t = {}))[s] = e[s]);
  return t;
}, Dl = (e, t) => {
  const s = {};
  for (const n in e)
    (!Hn(n) || !(n.slice(9) in t)) && (s[n] = e[n]);
  return s;
};
function Rl(e, t, s) {
  const { props: n, children: r, component: i } = e, { props: o, children: l, patchFlag: c } = t, u = i.emitsOptions;
  if (t.dirs || t.transition)
    return !0;
  if (s && c >= 0) {
    if (c & 1024)
      return !0;
    if (c & 16)
      return n ? xr(n, o, u) : !!o;
    if (c & 8) {
      const a = t.dynamicProps;
      for (let d = 0; d < a.length; d++) {
        const y = a[d];
        if (Vi(o, n, y) && !Zs(u, y))
          return !0;
      }
    }
  } else
    return (r || l) && (!l || !l.$stable) ? !0 : n === o ? !1 : n ? o ? xr(n, o, u) : !0 : !!o;
  return !1;
}
function xr(e, t, s) {
  const n = Object.keys(t);
  if (n.length !== Object.keys(e).length)
    return !0;
  for (let r = 0; r < n.length; r++) {
    const i = n[r];
    if (Vi(t, e, i) && !Zs(s, i))
      return !0;
  }
  return !1;
}
function Vi(e, t, s) {
  const n = e[s], r = t[s];
  return s === "style" && oe(n) && oe(r) ? !us(n, r) : n !== r;
}
function jl({ vnode: e, parent: t }, s) {
  for (; t; ) {
    const n = t.subTree;
    if (n.suspense && n.suspense.activeBranch === e && (n.el = e.el), n === e)
      (e = t.vnode).el = s, t = t.parent;
    else
      break;
  }
}
const Wi = {}, Bi = () => Object.create(Wi), Ui = (e) => Object.getPrototypeOf(e) === Wi;
function Nl(e, t, s, n = !1) {
  const r = {}, i = Bi();
  e.propsDefaults = /* @__PURE__ */ Object.create(null), Ki(e, t, r, i);
  for (const o in e.propsOptions[0])
    o in r || (r[o] = void 0);
  s ? e.props = n ? r : /* @__PURE__ */ qo(r) : e.type.props ? e.props = r : e.props = i, e.attrs = i;
}
function Vl(e, t, s, n) {
  const {
    props: r,
    attrs: i,
    vnode: { patchFlag: o }
  } = e, l = /* @__PURE__ */ re(r), [c] = e.propsOptions;
  let u = !1;
  if (
    // always force full diff in dev
    // - #1942 if hmr is enabled with sfc component
    // - vite#872 non-sfc component used by sfc component
    (n || o > 0) && !(o & 16)
  ) {
    if (o & 8) {
      const a = e.vnode.dynamicProps;
      for (let d = 0; d < a.length; d++) {
        let y = a[d];
        if (Zs(e.emitsOptions, y))
          continue;
        const $ = t[y];
        if (c)
          if (ie(i, y))
            $ !== i[y] && (i[y] = $, u = !0);
          else {
            const H = je(y);
            r[H] = Sn(
              c,
              l,
              H,
              $,
              e,
              !1
            );
          }
        else
          $ !== i[y] && (i[y] = $, u = !0);
      }
    }
  } else {
    Ki(e, t, r, i) && (u = !0);
    let a;
    for (const d in l)
      (!t || // for camelCase
      !ie(t, d) && // it's possible the original props was passed in as kebab-case
      // and converted to camelCase (#955)
      ((a = Tt(d)) === d || !ie(t, a))) && (c ? s && // for camelCase
      (s[d] !== void 0 || // for kebab-case
      s[a] !== void 0) && (r[d] = Sn(
        c,
        l,
        d,
        void 0,
        e,
        !0
      )) : delete r[d]);
    if (i !== l)
      for (const d in i)
        (!t || !ie(t, d)) && (delete i[d], u = !0);
  }
  u && ot(e.attrs, "set", "");
}
function Ki(e, t, s, n) {
  const [r, i] = e.propsOptions;
  let o = !1, l;
  if (t)
    for (let c in t) {
      if (Jt(c))
        continue;
      const u = t[c];
      let a;
      r && ie(r, a = je(c)) ? !i || !i.includes(a) ? s[a] = u : (l || (l = {}))[a] = u : Zs(e.emitsOptions, c) || (!(c in n) || u !== n[c]) && (n[c] = u, o = !0);
    }
  if (i) {
    const c = /* @__PURE__ */ re(s), u = l || ae;
    for (let a = 0; a < i.length; a++) {
      const d = i[a];
      s[d] = Sn(
        r,
        c,
        d,
        u[d],
        e,
        !ie(u, d)
      );
    }
  }
  return o;
}
function Sn(e, t, s, n, r, i) {
  const o = e[s];
  if (o != null) {
    const l = ie(o, "default");
    if (l && n === void 0) {
      const c = o.default;
      if (o.type !== Function && !o.skipFactory && te(c)) {
        const { propsDefaults: u } = r;
        if (s in u)
          n = u[s];
        else {
          const a = hs(r);
          n = u[s] = c.call(
            null,
            t
          ), a();
        }
      } else
        n = c;
      r.ce && r.ce._setProp(s, n);
    }
    o[
      0
      /* shouldCast */
    ] && (i && !l ? n = !1 : o[
      1
      /* shouldCastTrue */
    ] && (n === "" || n === Tt(s)) && (n = !0));
  }
  return n;
}
const Wl = /* @__PURE__ */ new WeakMap();
function Gi(e, t, s = !1) {
  const n = s ? Wl : t.propsCache, r = n.get(e);
  if (r)
    return r;
  const i = e.props, o = {}, l = [];
  let c = !1;
  if (!te(e)) {
    const a = (d) => {
      c = !0;
      const [y, $] = Gi(d, t, !0);
      ve(o, y), $ && l.push(...$);
    };
    !s && t.mixins.length && t.mixins.forEach(a), e.extends && a(e.extends), e.mixins && e.mixins.forEach(a);
  }
  if (!i && !c)
    return oe(e) && n.set(e, It), It;
  if (G(i))
    for (let a = 0; a < i.length; a++) {
      const d = je(i[a]);
      br(d) && (o[d] = ae);
    }
  else if (i)
    for (const a in i) {
      const d = je(a);
      if (br(d)) {
        const y = i[a], $ = o[d] = G(y) || te(y) ? { type: y } : ve({}, y), H = $.type;
        let O = !1, Y = !0;
        if (G(H))
          for (let B = 0; B < H.length; ++B) {
            const E = H[B], z = te(E) && E.name;
            if (z === "Boolean") {
              O = !0;
              break;
            } else z === "String" && (Y = !1);
          }
        else
          O = te(H) && H.name === "Boolean";
        $[
          0
          /* shouldCast */
        ] = O, $[
          1
          /* shouldCastTrue */
        ] = Y, (O || ie($, "default")) && l.push(d);
      }
    }
  const u = [o, l];
  return oe(e) && n.set(e, u), u;
}
function br(e) {
  return e[0] !== "$" && !Jt(e);
}
const Jn = (e) => e === "_" || e === "_ctx" || e === "$stable", zn = (e) => G(e) ? e.map(Qe) : [Qe(e)], Bl = (e, t, s) => {
  if (t._n)
    return t;
  const n = ds((...r) => zn(t(...r)), s);
  return n._c = !1, n;
}, qi = (e, t, s) => {
  const n = e._ctx;
  for (const r in e) {
    if (Jn(r)) continue;
    const i = e[r];
    if (te(i))
      t[r] = Bl(r, i, n);
    else if (i != null) {
      const o = zn(i);
      t[r] = () => o;
    }
  }
}, Ji = (e, t) => {
  const s = zn(t);
  e.slots.default = () => s;
}, zi = (e, t, s) => {
  for (const n in t)
    (s || !Jn(n)) && (e[n] = t[n]);
}, Ul = (e, t, s) => {
  const n = e.slots = Bi();
  if (e.vnode.shapeFlag & 32) {
    const r = t._;
    r ? (zi(n, t, s), s && Xr(n, "_", r, !0)) : qi(t, n);
  } else t && Ji(e, t);
}, Kl = (e, t, s) => {
  const { vnode: n, slots: r } = e;
  let i = !0, o = ae;
  if (n.shapeFlag & 32) {
    const l = t._;
    l ? s && l === 1 ? i = !1 : zi(r, t, s) : (i = !t.$stable, qi(t, r)), o = t;
  } else t && (Ji(e, t), o = { default: 1 });
  if (i)
    for (const l in r)
      !Jn(l) && o[l] == null && delete r[l];
}, xe = Zl;
function Gl(e) {
  return ql(e);
}
function ql(e, t) {
  const s = Ws();
  s.__VUE__ = !0;
  const {
    insert: n,
    remove: r,
    patchProp: i,
    createElement: o,
    createText: l,
    createComment: c,
    setText: u,
    setElementText: a,
    parentNode: d,
    nextSibling: y,
    setScopeId: $ = et,
    insertStaticContent: H
  } = e, O = (f, p, v, T = null, w = null, k = null, I = void 0, L = null, P = !!p.dynamicChildren) => {
    if (f === p)
      return;
    f && !wt(f, p) && (T = gs(f), Be(f, w, k, !0), f = null), p.patchFlag === -2 && (P = !1, p.dynamicChildren = null);
    const { type: C, ref: J, shapeFlag: F } = p;
    switch (C) {
      case Ys:
        Y(f, p, v, T);
        break;
      case $e:
        B(f, p, v, T);
        break;
      case un:
        f == null && E(p, v, T, I);
        break;
      case Z:
        D(
          f,
          p,
          v,
          T,
          w,
          k,
          I,
          L,
          P
        );
        break;
      default:
        F & 1 ? V(
          f,
          p,
          v,
          T,
          w,
          k,
          I,
          L,
          P
        ) : F & 6 ? M(
          f,
          p,
          v,
          T,
          w,
          k,
          I,
          L,
          P
        ) : (F & 64 || F & 128) && C.process(
          f,
          p,
          v,
          T,
          w,
          k,
          I,
          L,
          P,
          Nt
        );
    }
    J != null && w ? Qt(J, f && f.ref, k, p || f, !p) : J == null && f && f.ref != null && Qt(f.ref, null, k, f, !0);
  }, Y = (f, p, v, T) => {
    if (f == null)
      n(
        p.el = l(p.children),
        v,
        T
      );
    else {
      const w = p.el = f.el;
      p.children !== f.children && u(w, p.children);
    }
  }, B = (f, p, v, T) => {
    f == null ? n(
      p.el = c(p.children || ""),
      v,
      T
    ) : p.el = f.el;
  }, E = (f, p, v, T) => {
    [f.el, f.anchor] = H(
      f.children,
      p,
      v,
      T,
      f.el,
      f.anchor
    );
  }, z = ({ el: f, anchor: p }, v, T) => {
    let w;
    for (; f && f !== p; )
      w = y(f), n(f, v, T), f = w;
    n(p, v, T);
  }, x = ({ el: f, anchor: p }) => {
    let v;
    for (; f && f !== p; )
      v = y(f), r(f), f = v;
    r(p);
  }, V = (f, p, v, T, w, k, I, L, P) => {
    if (p.type === "svg" ? I = "svg" : p.type === "math" && (I = "mathml"), f == null)
      X(
        p,
        v,
        T,
        w,
        k,
        I,
        L,
        P
      );
    else {
      const C = f.el && f.el._isVueCE ? f.el : null;
      try {
        C && C._beginPatch(), S(
          f,
          p,
          w,
          k,
          I,
          L,
          P
        );
      } finally {
        C && C._endPatch();
      }
    }
  }, X = (f, p, v, T, w, k, I, L) => {
    let P, C;
    const { props: J, shapeFlag: F, transition: K, dirs: Q } = f;
    if (P = f.el = o(
      f.type,
      k,
      J && J.is,
      J
    ), F & 8 ? a(P, f.children) : F & 16 && se(
      f.children,
      P,
      null,
      T,
      w,
      an(f, k),
      I,
      L
    ), Q && mt(f, null, T, "created"), ee(P, f, f.scopeId, I, T), J) {
      for (const fe in J)
        fe !== "value" && !Jt(fe) && i(P, fe, null, J[fe], k, T);
      "value" in J && i(P, "value", null, J.value, k), (C = J.onVnodeBeforeMount) && qe(C, T, f);
    }
    Q && mt(f, null, T, "beforeMount");
    const ne = Jl(w, K);
    ne && K.beforeEnter(P), n(P, p, v), ((C = J && J.onVnodeMounted) || ne || Q) && xe(() => {
      C && qe(C, T, f), ne && K.enter(P), Q && mt(f, null, T, "mounted");
    }, w);
  }, ee = (f, p, v, T, w) => {
    if (v && $(f, v), T)
      for (let k = 0; k < T.length; k++)
        $(f, T[k]);
    if (w) {
      let k = w.subTree;
      if (p === k || Qi(k.type) && (k.ssContent === p || k.ssFallback === p)) {
        const I = w.vnode;
        ee(
          f,
          I,
          I.scopeId,
          I.slotScopeIds,
          w.parent
        );
      }
    }
  }, se = (f, p, v, T, w, k, I, L, P = 0) => {
    for (let C = P; C < f.length; C++) {
      const J = f[C] = L ? it(f[C]) : Qe(f[C]);
      O(
        null,
        J,
        p,
        v,
        T,
        w,
        k,
        I,
        L
      );
    }
  }, S = (f, p, v, T, w, k, I) => {
    const L = p.el = f.el;
    let { patchFlag: P, dynamicChildren: C, dirs: J } = p;
    P |= f.patchFlag & 16;
    const F = f.props || ae, K = p.props || ae;
    let Q;
    if (v && vt(v, !1), (Q = K.onVnodeBeforeUpdate) && qe(Q, v, p, f), J && mt(p, f, v, "beforeUpdate"), v && vt(v, !0), (F.innerHTML && K.innerHTML == null || F.textContent && K.textContent == null) && a(L, ""), C ? j(
      f.dynamicChildren,
      C,
      L,
      v,
      T,
      an(p, w),
      k
    ) : I || ue(
      f,
      p,
      L,
      null,
      v,
      T,
      an(p, w),
      k,
      !1
    ), P > 0) {
      if (P & 16)
        q(L, F, K, v, w);
      else if (P & 2 && F.class !== K.class && i(L, "class", null, K.class, w), P & 4 && i(L, "style", F.style, K.style, w), P & 8) {
        const ne = p.dynamicProps;
        for (let fe = 0; fe < ne.length; fe++) {
          const le = ne[fe], Me = F[le], Pe = K[le];
          (Pe !== Me || le === "value") && i(L, le, Me, Pe, w, v);
        }
      }
      P & 1 && f.children !== p.children && a(L, p.children);
    } else !I && C == null && q(L, F, K, v, w);
    ((Q = K.onVnodeUpdated) || J) && xe(() => {
      Q && qe(Q, v, p, f), J && mt(p, f, v, "updated");
    }, T);
  }, j = (f, p, v, T, w, k, I) => {
    for (let L = 0; L < p.length; L++) {
      const P = f[L], C = p[L], J = (
        // oldVNode may be an errored async setup() component inside Suspense
        // which will not have a mounted element
        P.el && // - In the case of a Fragment, we need to provide the actual parent
        // of the Fragment itself so it can move its children.
        (P.type === Z || // - In the case of different nodes, there is going to be a replacement
        // which also requires the correct parent container
        !wt(P, C) || // - In the case of a component, it could contain anything.
        P.shapeFlag & 198) ? d(P.el) : (
          // In other cases, the parent container is not actually used so we
          // just pass the block element here to avoid a DOM parentNode call.
          v
        )
      );
      O(
        P,
        C,
        J,
        null,
        T,
        w,
        k,
        I,
        !0
      );
    }
  }, q = (f, p, v, T, w) => {
    if (p !== v) {
      if (p !== ae)
        for (const k in p)
          !Jt(k) && !(k in v) && i(
            f,
            k,
            p[k],
            null,
            w,
            T
          );
      for (const k in v) {
        if (Jt(k)) continue;
        const I = v[k], L = p[k];
        I !== L && k !== "value" && i(f, k, L, I, w, T);
      }
      "value" in v && i(f, "value", p.value, v.value, w);
    }
  }, D = (f, p, v, T, w, k, I, L, P) => {
    const C = p.el = f ? f.el : l(""), J = p.anchor = f ? f.anchor : l("");
    let { patchFlag: F, dynamicChildren: K, slotScopeIds: Q } = p;
    Q && (L = L ? L.concat(Q) : Q), f == null ? (n(C, v, T), n(J, v, T), se(
      // #10007
      // such fragment like `<></>` will be compiled into
      // a fragment which doesn't have a children.
      // In this case fallback to an empty array
      p.children || [],
      v,
      J,
      w,
      k,
      I,
      L,
      P
    )) : F > 0 && F & 64 && K && // #2715 the previous fragment could've been a BAILed one as a result
    // of renderSlot() with no valid children
    f.dynamicChildren && f.dynamicChildren.length === K.length ? (j(
      f.dynamicChildren,
      K,
      v,
      w,
      k,
      I,
      L
    ), // #2080 if the stable fragment has a key, it's a <template v-for> that may
    //  get moved around. Make sure all root level vnodes inherit el.
    // #2134 or if it's a component root, it may also get moved around
    // as the component is being moved.
    (p.key != null || w && p === w.subTree) && Zn(
      f,
      p,
      !0
      /* shallow */
    )) : ue(
      f,
      p,
      v,
      J,
      w,
      k,
      I,
      L,
      P
    );
  }, M = (f, p, v, T, w, k, I, L, P) => {
    p.slotScopeIds = L, f == null ? p.shapeFlag & 512 ? w.ctx.activate(
      p,
      v,
      T,
      I,
      P
    ) : R(
      p,
      v,
      T,
      w,
      k,
      I,
      P
    ) : b(f, p, P);
  }, R = (f, p, v, T, w, k, I) => {
    const L = f.component = nc(
      f,
      T,
      w
    );
    if (qs(f) && (L.ctx.renderer = Nt), rc(L, !1, I), L.asyncDep) {
      if (w && w.registerDep(L, W, I), !f.el) {
        const P = L.subTree = ye($e);
        B(null, P, p, v), f.placeholder = P.el;
      }
    } else
      W(
        L,
        f,
        p,
        v,
        w,
        k,
        I
      );
  }, b = (f, p, v) => {
    const T = p.component = f.component;
    if (Rl(f, p, v))
      if (T.asyncDep && !T.asyncResolved) {
        pe(T, p, v);
        return;
      } else
        T.next = p, T.update();
    else
      p.el = f.el, T.vnode = p;
  }, W = (f, p, v, T, w, k, I) => {
    const L = () => {
      if (f.isMounted) {
        let { next: F, bu: K, u: Q, parent: ne, vnode: fe } = f;
        {
          const Ke = Zi(f);
          if (Ke) {
            F && (F.el = fe.el, pe(f, F, I)), Ke.asyncDep.then(() => {
              xe(() => {
                f.isUnmounted || C();
              }, w);
            });
            return;
          }
        }
        let le = F, Me;
        vt(f, !1), F ? (F.el = fe.el, pe(f, F, I)) : F = fe, K && ws(K), (Me = F.props && F.props.onVnodeBeforeUpdate) && qe(Me, ne, F, fe), vt(f, !0);
        const Pe = yr(f), Ue = f.subTree;
        f.subTree = Pe, O(
          Ue,
          Pe,
          // parent may have changed if it's in a teleport
          d(Ue.el),
          // anchor may have changed if it's in a fragment
          gs(Ue),
          f,
          w,
          k
        ), F.el = Pe.el, le === null && jl(f, Pe.el), Q && xe(Q, w), (Me = F.props && F.props.onVnodeUpdated) && xe(
          () => qe(Me, ne, F, fe),
          w
        );
      } else {
        let F;
        const { el: K, props: Q } = p, { bm: ne, m: fe, parent: le, root: Me, type: Pe } = f, Ue = Xt(p);
        vt(f, !1), ne && ws(ne), !Ue && (F = Q && Q.onVnodeBeforeMount) && qe(F, le, p), vt(f, !0);
        {
          Me.ce && Me.ce._hasShadowRoot() && Me.ce._injectChildStyle(
            Pe,
            f.parent ? f.parent.type : void 0
          );
          const Ke = f.subTree = yr(f);
          O(
            null,
            Ke,
            v,
            T,
            f,
            w,
            k
          ), p.el = Ke.el;
        }
        if (fe && xe(fe, w), !Ue && (F = Q && Q.onVnodeMounted)) {
          const Ke = p;
          xe(
            () => qe(F, le, Ke),
            w
          );
        }
        (p.shapeFlag & 256 || le && Xt(le.vnode) && le.vnode.shapeFlag & 256) && f.a && xe(f.a, w), f.isMounted = !0, p = v = T = null;
      }
    };
    f.scope.on();
    const P = f.effect = new ri(L);
    f.scope.off();
    const C = f.update = P.run.bind(P), J = f.job = P.runIfDirty.bind(P);
    J.i = f, J.id = f.uid, P.scheduler = () => qn(J), vt(f, !0), C();
  }, pe = (f, p, v) => {
    p.component = f;
    const T = f.vnode.props;
    f.vnode = p, f.next = null, Vl(f, p.props, T, v), Kl(f, p.children, v), ct(), cr(f), at();
  }, ue = (f, p, v, T, w, k, I, L, P = !1) => {
    const C = f && f.children, J = f ? f.shapeFlag : 0, F = p.children, { patchFlag: K, shapeFlag: Q } = p;
    if (K > 0) {
      if (K & 128) {
        _s(
          C,
          F,
          v,
          T,
          w,
          k,
          I,
          L,
          P
        );
        return;
      } else if (K & 256) {
        _t(
          C,
          F,
          v,
          T,
          w,
          k,
          I,
          L,
          P
        );
        return;
      }
    }
    Q & 8 ? (J & 16 && jt(C, w, k), F !== C && a(v, F)) : J & 16 ? Q & 16 ? _s(
      C,
      F,
      v,
      T,
      w,
      k,
      I,
      L,
      P
    ) : jt(C, w, k, !0) : (J & 8 && a(v, ""), Q & 16 && se(
      F,
      v,
      T,
      w,
      k,
      I,
      L,
      P
    ));
  }, _t = (f, p, v, T, w, k, I, L, P) => {
    f = f || It, p = p || It;
    const C = f.length, J = p.length, F = Math.min(C, J);
    let K;
    for (K = 0; K < F; K++) {
      const Q = p[K] = P ? it(p[K]) : Qe(p[K]);
      O(
        f[K],
        Q,
        v,
        null,
        w,
        k,
        I,
        L,
        P
      );
    }
    C > J ? jt(
      f,
      w,
      k,
      !0,
      !1,
      F
    ) : se(
      p,
      v,
      T,
      w,
      k,
      I,
      L,
      P,
      F
    );
  }, _s = (f, p, v, T, w, k, I, L, P) => {
    let C = 0;
    const J = p.length;
    let F = f.length - 1, K = J - 1;
    for (; C <= F && C <= K; ) {
      const Q = f[C], ne = p[C] = P ? it(p[C]) : Qe(p[C]);
      if (wt(Q, ne))
        O(
          Q,
          ne,
          v,
          null,
          w,
          k,
          I,
          L,
          P
        );
      else
        break;
      C++;
    }
    for (; C <= F && C <= K; ) {
      const Q = f[F], ne = p[K] = P ? it(p[K]) : Qe(p[K]);
      if (wt(Q, ne))
        O(
          Q,
          ne,
          v,
          null,
          w,
          k,
          I,
          L,
          P
        );
      else
        break;
      F--, K--;
    }
    if (C > F) {
      if (C <= K) {
        const Q = K + 1, ne = Q < J ? p[Q].el : T;
        for (; C <= K; )
          O(
            null,
            p[C] = P ? it(p[C]) : Qe(p[C]),
            v,
            ne,
            w,
            k,
            I,
            L,
            P
          ), C++;
      }
    } else if (C > K)
      for (; C <= F; )
        Be(f[C], w, k, !0), C++;
    else {
      const Q = C, ne = C, fe = /* @__PURE__ */ new Map();
      for (C = ne; C <= K; C++) {
        const Ee = p[C] = P ? it(p[C]) : Qe(p[C]);
        Ee.key != null && fe.set(Ee.key, C);
      }
      let le, Me = 0;
      const Pe = K - ne + 1;
      let Ue = !1, Ke = 0;
      const Vt = new Array(Pe);
      for (C = 0; C < Pe; C++) Vt[C] = 0;
      for (C = Q; C <= F; C++) {
        const Ee = f[C];
        if (Me >= Pe) {
          Be(Ee, w, k, !0);
          continue;
        }
        let Ge;
        if (Ee.key != null)
          Ge = fe.get(Ee.key);
        else
          for (le = ne; le <= K; le++)
            if (Vt[le - ne] === 0 && wt(Ee, p[le])) {
              Ge = le;
              break;
            }
        Ge === void 0 ? Be(Ee, w, k, !0) : (Vt[Ge - ne] = C + 1, Ge >= Ke ? Ke = Ge : Ue = !0, O(
          Ee,
          p[Ge],
          v,
          null,
          w,
          k,
          I,
          L,
          P
        ), Me++);
      }
      const tr = Ue ? zl(Vt) : It;
      for (le = tr.length - 1, C = Pe - 1; C >= 0; C--) {
        const Ee = ne + C, Ge = p[Ee], sr = p[Ee + 1], nr = Ee + 1 < J ? (
          // #13559, #14173 fallback to el placeholder for unresolved async component
          sr.el || Yi(sr)
        ) : T;
        Vt[C] === 0 ? O(
          null,
          Ge,
          v,
          nr,
          w,
          k,
          I,
          L,
          P
        ) : Ue && (le < 0 || C !== tr[le] ? gt(Ge, v, nr, 2) : le--);
      }
    }
  }, gt = (f, p, v, T, w = null) => {
    const { el: k, type: I, transition: L, children: P, shapeFlag: C } = f;
    if (C & 6) {
      gt(f.component.subTree, p, v, T);
      return;
    }
    if (C & 128) {
      f.suspense.move(p, v, T);
      return;
    }
    if (C & 64) {
      I.move(f, p, v, Nt);
      return;
    }
    if (I === Z) {
      n(k, p, v);
      for (let F = 0; F < P.length; F++)
        gt(P[F], p, v, T);
      n(f.anchor, p, v);
      return;
    }
    if (I === un) {
      z(f, p, v);
      return;
    }
    if (T !== 2 && C & 1 && L)
      if (T === 0)
        L.beforeEnter(k), n(k, p, v), xe(() => L.enter(k), w);
      else {
        const { leave: F, delayLeave: K, afterLeave: Q } = L, ne = () => {
          f.ctx.isUnmounted ? r(k) : n(k, p, v);
        }, fe = () => {
          k._isLeaving && k[Ze](
            !0
            /* cancelled */
          ), F(k, () => {
            ne(), Q && Q();
          });
        };
        K ? K(k, ne, fe) : fe();
      }
    else
      n(k, p, v);
  }, Be = (f, p, v, T = !1, w = !1) => {
    const {
      type: k,
      props: I,
      ref: L,
      children: P,
      dynamicChildren: C,
      shapeFlag: J,
      patchFlag: F,
      dirs: K,
      cacheIndex: Q
    } = f;
    if (F === -2 && (w = !1), L != null && (ct(), Qt(L, null, v, f, !0), at()), Q != null && (p.renderCache[Q] = void 0), J & 256) {
      p.ctx.deactivate(f);
      return;
    }
    const ne = J & 1 && K, fe = !Xt(f);
    let le;
    if (fe && (le = I && I.onVnodeBeforeUnmount) && qe(le, p, f), J & 6)
      ho(f.component, v, T);
    else {
      if (J & 128) {
        f.suspense.unmount(v, T);
        return;
      }
      ne && mt(f, null, p, "beforeUnmount"), J & 64 ? f.type.remove(
        f,
        p,
        v,
        Nt,
        T
      ) : C && // #5154
      // when v-once is used inside a block, setBlockTracking(-1) marks the
      // parent block with hasOnce: true
      // so that it doesn't take the fast path during unmount - otherwise
      // components nested in v-once are never unmounted.
      !C.hasOnce && // #1153: fast path should not be taken for non-stable (v-for) fragments
      (k !== Z || F > 0 && F & 64) ? jt(
        C,
        p,
        v,
        !1,
        !0
      ) : (k === Z && F & 384 || !w && J & 16) && jt(P, p, v), T && Xn(f);
    }
    (fe && (le = I && I.onVnodeUnmounted) || ne) && xe(() => {
      le && qe(le, p, f), ne && mt(f, null, p, "unmounted");
    }, v);
  }, Xn = (f) => {
    const { type: p, el: v, anchor: T, transition: w } = f;
    if (p === Z) {
      po(v, T);
      return;
    }
    if (p === un) {
      x(f);
      return;
    }
    const k = () => {
      r(v), w && !w.persisted && w.afterLeave && w.afterLeave();
    };
    if (f.shapeFlag & 1 && w && !w.persisted) {
      const { leave: I, delayLeave: L } = w, P = () => I(v, k);
      L ? L(f.el, k, P) : P();
    } else
      k();
  }, po = (f, p) => {
    let v;
    for (; f !== p; )
      v = y(f), r(f), f = v;
    r(p);
  }, ho = (f, p, v) => {
    const { bum: T, scope: w, job: k, subTree: I, um: L, m: P, a: C } = f;
    wr(P), wr(C), T && ws(T), w.stop(), k && (k.flags |= 8, Be(I, f, p, v)), L && xe(L, p), xe(() => {
      f.isUnmounted = !0;
    }, p);
  }, jt = (f, p, v, T = !1, w = !1, k = 0) => {
    for (let I = k; I < f.length; I++)
      Be(f[I], p, v, T, w);
  }, gs = (f) => {
    if (f.shapeFlag & 6)
      return gs(f.component.subTree);
    if (f.shapeFlag & 128)
      return f.suspense.next();
    const p = y(f.anchor || f.el), v = p && p[Ti];
    return v ? y(v) : p;
  };
  let en = !1;
  const er = (f, p, v) => {
    let T;
    f == null ? p._vnode && (Be(p._vnode, null, null, !0), T = p._vnode.component) : O(
      p._vnode || null,
      f,
      p,
      null,
      null,
      null,
      v
    ), p._vnode = f, en || (en = !0, cr(T), bi(), en = !1);
  }, Nt = {
    p: O,
    um: Be,
    m: gt,
    r: Xn,
    mt: R,
    mc: se,
    pc: ue,
    pbc: j,
    n: gs,
    o: e
  };
  return {
    render: er,
    hydrate: void 0,
    createApp: El(er)
  };
}
function an({ type: e, props: t }, s) {
  return s === "svg" && e === "foreignObject" || s === "mathml" && e === "annotation-xml" && t && t.encoding && t.encoding.includes("html") ? void 0 : s;
}
function vt({ effect: e, job: t }, s) {
  s ? (e.flags |= 32, t.flags |= 4) : (e.flags &= -33, t.flags &= -5);
}
function Jl(e, t) {
  return (!e || e && !e.pendingBranch) && t && !t.persisted;
}
function Zn(e, t, s = !1) {
  const n = e.children, r = t.children;
  if (G(n) && G(r))
    for (let i = 0; i < n.length; i++) {
      const o = n[i];
      let l = r[i];
      l.shapeFlag & 1 && !l.dynamicChildren && ((l.patchFlag <= 0 || l.patchFlag === 32) && (l = r[i] = it(r[i]), l.el = o.el), !s && l.patchFlag !== -2 && Zn(o, l)), l.type === Ys && (l.patchFlag === -1 && (l = r[i] = it(l)), l.el = o.el), l.type === $e && !l.el && (l.el = o.el);
    }
}
function zl(e) {
  const t = e.slice(), s = [0];
  let n, r, i, o, l;
  const c = e.length;
  for (n = 0; n < c; n++) {
    const u = e[n];
    if (u !== 0) {
      if (r = s[s.length - 1], e[r] < u) {
        t[n] = r, s.push(n);
        continue;
      }
      for (i = 0, o = s.length - 1; i < o; )
        l = i + o >> 1, e[s[l]] < u ? i = l + 1 : o = l;
      u < e[s[i]] && (i > 0 && (t[n] = s[i - 1]), s[i] = n);
    }
  }
  for (i = s.length, o = s[i - 1]; i-- > 0; )
    s[i] = o, o = t[o];
  return s;
}
function Zi(e) {
  const t = e.subTree.component;
  if (t)
    return t.asyncDep && !t.asyncResolved ? t : Zi(t);
}
function wr(e) {
  if (e)
    for (let t = 0; t < e.length; t++)
      e[t].flags |= 8;
}
function Yi(e) {
  if (e.placeholder)
    return e.placeholder;
  const t = e.component;
  return t ? Yi(t.subTree) : null;
}
const Qi = (e) => e.__isSuspense;
function Zl(e, t) {
  t && t.pendingBranch ? G(e) ? t.effects.push(...e) : t.effects.push(e) : il(e);
}
const Z = /* @__PURE__ */ Symbol.for("v-fgt"), Ys = /* @__PURE__ */ Symbol.for("v-txt"), $e = /* @__PURE__ */ Symbol.for("v-cmt"), un = /* @__PURE__ */ Symbol.for("v-stc"), ts = [];
let Ie = null;
function _(e = !1) {
  ts.push(Ie = e ? null : []);
}
function Yl() {
  ts.pop(), Ie = ts[ts.length - 1] || null;
}
let os = 1;
function Es(e, t = !1) {
  os += e, e < 0 && Ie && t && (Ie.hasOnce = !0);
}
function Xi(e) {
  return e.dynamicChildren = os > 0 ? Ie || It : null, Yl(), os > 0 && Ie && Ie.push(e), e;
}
function m(e, t, s, n, r, i) {
  return Xi(
    h(
      e,
      t,
      s,
      n,
      r,
      i,
      !0
    )
  );
}
function ps(e, t, s, n, r) {
  return Xi(
    ye(
      e,
      t,
      s,
      n,
      r,
      !0
    )
  );
}
function Is(e) {
  return e ? e.__v_isVNode === !0 : !1;
}
function wt(e, t) {
  return e.type === t.type && e.key === t.key;
}
const eo = ({ key: e }) => e ?? null, $s = ({
  ref: e,
  ref_key: t,
  ref_for: s
}) => (typeof e == "number" && (e = "" + e), e != null ? _e(e) || /* @__PURE__ */ we(e) || te(e) ? { i: Fe, r: e, k: t, f: !!s } : e : null);
function h(e, t = null, s = null, n = 0, r = null, i = e === Z ? 0 : 1, o = !1, l = !1) {
  const c = {
    __v_isVNode: !0,
    __v_skip: !0,
    type: e,
    props: t,
    key: t && eo(t),
    ref: t && $s(t),
    scopeId: ki,
    slotScopeIds: null,
    children: s,
    component: null,
    suspense: null,
    ssContent: null,
    ssFallback: null,
    dirs: null,
    transition: null,
    el: null,
    anchor: null,
    target: null,
    targetStart: null,
    targetAnchor: null,
    staticCount: 0,
    shapeFlag: i,
    patchFlag: n,
    dynamicProps: r,
    dynamicChildren: null,
    appContext: null,
    ctx: Fe
  };
  return l ? (Yn(c, s), i & 128 && e.normalize(c)) : s && (c.shapeFlag |= _e(s) ? 8 : 16), os > 0 && // avoid a block node from tracking itself
  !o && // has current parent block
  Ie && // presence of a patch flag indicates this node needs patching on updates.
  // component nodes also should always be patched, because even if the
  // component doesn't need to update, it needs to persist the instance on to
  // the next vnode so that it can be properly unmounted later.
  (c.patchFlag > 0 || i & 6) && // the EVENTS flag is only for hydration and if it is the only flag, the
  // vnode should not be considered dynamic due to handler caching.
  c.patchFlag !== 32 && Ie.push(c), c;
}
const ye = Ql;
function Ql(e, t = null, s = null, n = 0, r = null, i = !1) {
  if ((!e || e === Cl) && (e = $e), Is(e)) {
    const l = ht(
      e,
      t,
      !0
      /* mergeRef: true */
    );
    return s && Yn(l, s), os > 0 && !i && Ie && (l.shapeFlag & 6 ? Ie[Ie.indexOf(e)] = l : Ie.push(l)), l.patchFlag = -2, l;
  }
  if (cc(e) && (e = e.__vccOpts), t) {
    t = Xl(t);
    let { class: l, style: c } = t;
    l && !_e(l) && (t.class = he(l)), oe(c) && (/* @__PURE__ */ Gn(c) && !G(c) && (c = ve({}, c)), t.style = jn(c));
  }
  const o = _e(e) ? 1 : Qi(e) ? 128 : Si(e) ? 64 : oe(e) ? 4 : te(e) ? 2 : 0;
  return h(
    e,
    t,
    s,
    n,
    r,
    o,
    i,
    !0
  );
}
function Xl(e) {
  return e ? /* @__PURE__ */ Gn(e) || Ui(e) ? ve({}, e) : e : null;
}
function ht(e, t, s = !1, n = !1) {
  const { props: r, ref: i, patchFlag: o, children: l, transition: c } = e, u = t ? ec(r || {}, t) : r, a = {
    __v_isVNode: !0,
    __v_skip: !0,
    type: e.type,
    props: u,
    key: u && eo(u),
    ref: t && t.ref ? (
      // #2078 in the case of <component :is="vnode" ref="extra"/>
      // if the vnode itself already has a ref, cloneVNode will need to merge
      // the refs so the single vnode can be set on multiple refs
      s && i ? G(i) ? i.concat($s(t)) : [i, $s(t)] : $s(t)
    ) : i,
    scopeId: e.scopeId,
    slotScopeIds: e.slotScopeIds,
    children: l,
    target: e.target,
    targetStart: e.targetStart,
    targetAnchor: e.targetAnchor,
    staticCount: e.staticCount,
    shapeFlag: e.shapeFlag,
    // if the vnode is cloned with extra props, we can no longer assume its
    // existing patch flag to be reliable and need to add the FULL_PROPS flag.
    // note: preserve flag for fragments since they use the flag for children
    // fast paths only.
    patchFlag: t && e.type !== Z ? o === -1 ? 16 : o | 16 : o,
    dynamicProps: e.dynamicProps,
    dynamicChildren: e.dynamicChildren,
    appContext: e.appContext,
    dirs: e.dirs,
    transition: c,
    // These should technically only be non-null on mounted VNodes. However,
    // they *should* be copied for kept-alive vnodes. So we just always copy
    // them since them being non-null during a mount doesn't affect the logic as
    // they will simply be overwritten.
    component: e.component,
    suspense: e.suspense,
    ssContent: e.ssContent && ht(e.ssContent),
    ssFallback: e.ssFallback && ht(e.ssFallback),
    placeholder: e.placeholder,
    el: e.el,
    anchor: e.anchor,
    ctx: e.ctx,
    ce: e.ce
  };
  return c && n && is(
    a,
    c.clone(a)
  ), a;
}
function me(e = " ", t = 0) {
  return ye(Ys, null, e, t);
}
function N(e = "", t = !1) {
  return t ? (_(), ps($e, null, e)) : ye($e, null, e);
}
function Qe(e) {
  return e == null || typeof e == "boolean" ? ye($e) : G(e) ? ye(
    Z,
    null,
    // #3666, avoid reference pollution when reusing vnode
    e.slice()
  ) : Is(e) ? it(e) : ye(Ys, null, String(e));
}
function it(e) {
  return e.el === null && e.patchFlag !== -1 || e.memo ? e : ht(e);
}
function Yn(e, t) {
  let s = 0;
  const { shapeFlag: n } = e;
  if (t == null)
    t = null;
  else if (G(t))
    s = 16;
  else if (typeof t == "object")
    if (n & 65) {
      const r = t.default;
      r && (r._c && (r._d = !1), Yn(e, r()), r._c && (r._d = !0));
      return;
    } else {
      s = 32;
      const r = t._;
      !r && !Ui(t) ? t._ctx = Fe : r === 3 && Fe && (Fe.slots._ === 1 ? t._ = 1 : (t._ = 2, e.patchFlag |= 1024));
    }
  else te(t) ? (t = { default: t, _ctx: Fe }, s = 32) : (t = String(t), n & 64 ? (s = 16, t = [me(t)]) : s = 8);
  e.children = t, e.shapeFlag |= s;
}
function ec(...e) {
  const t = {};
  for (let s = 0; s < e.length; s++) {
    const n = e[s];
    for (const r in n)
      if (r === "class")
        t.class !== n.class && (t.class = he([t.class, n.class]));
      else if (r === "style")
        t.style = jn([t.style, n.style]);
      else if (js(r)) {
        const i = t[r], o = n[r];
        o && i !== o && !(G(i) && i.includes(o)) && (t[r] = i ? [].concat(i, o) : o);
      } else r !== "" && (t[r] = n[r]);
  }
  return t;
}
function qe(e, t, s, n = null) {
  We(e, t, 7, [
    s,
    n
  ]);
}
const tc = ji();
let sc = 0;
function nc(e, t, s) {
  const n = e.type, r = (t ? t.appContext : e.appContext) || tc, i = {
    uid: sc++,
    vnode: e,
    type: n,
    parent: t,
    appContext: r,
    root: null,
    // to be immediately set
    next: null,
    subTree: null,
    // will be set synchronously right after creation
    effect: null,
    update: null,
    // will be set synchronously right after creation
    job: null,
    scope: new So(
      !0
      /* detached */
    ),
    render: null,
    proxy: null,
    exposed: null,
    exposeProxy: null,
    withProxy: null,
    provides: t ? t.provides : Object.create(r.provides),
    ids: t ? t.ids : ["", 0, 0],
    accessCache: null,
    renderCache: [],
    // local resolved assets
    components: null,
    directives: null,
    // resolved props and emits options
    propsOptions: Gi(n, r),
    emitsOptions: Ni(n, r),
    // emit
    emit: null,
    // to be set immediately
    emitted: null,
    // props default value
    propsDefaults: ae,
    // inheritAttrs
    inheritAttrs: n.inheritAttrs,
    // state
    ctx: ae,
    data: ae,
    props: ae,
    attrs: ae,
    slots: ae,
    refs: ae,
    setupState: ae,
    setupContext: null,
    // suspense related
    suspense: s,
    suspenseId: s ? s.pendingId : 0,
    asyncDep: null,
    asyncResolved: !1,
    // lifecycle hooks
    // not using enums here because it results in computed properties
    isMounted: !1,
    isUnmounted: !1,
    isDeactivated: !1,
    bc: null,
    c: null,
    bm: null,
    m: null,
    bu: null,
    u: null,
    um: null,
    bum: null,
    da: null,
    a: null,
    rtg: null,
    rtc: null,
    ec: null,
    sp: null
  };
  return i.ctx = { _: i }, i.root = t ? t.root : i, i.emit = Ol.bind(null, i), e.ce && e.ce(i), i;
}
let Te = null;
const to = () => Te || Fe;
let Os, Mn;
{
  const e = Ws(), t = (s, n) => {
    let r;
    return (r = e[s]) || (r = e[s] = []), r.push(n), (i) => {
      r.length > 1 ? r.forEach((o) => o(i)) : r[0](i);
    };
  };
  Os = t(
    "__VUE_INSTANCE_SETTERS__",
    (s) => Te = s
  ), Mn = t(
    "__VUE_SSR_SETTERS__",
    (s) => ls = s
  );
}
const hs = (e) => {
  const t = Te;
  return Os(e), e.scope.on(), () => {
    e.scope.off(), Os(t);
  };
}, kr = () => {
  Te && Te.scope.off(), Os(null);
};
function so(e) {
  return e.vnode.shapeFlag & 4;
}
let ls = !1;
function rc(e, t = !1, s = !1) {
  t && Mn(t);
  const { props: n, children: r } = e.vnode, i = so(e);
  Nl(e, n, i, t), Ul(e, r, s || t);
  const o = i ? ic(e, t) : void 0;
  return t && Mn(!1), o;
}
function ic(e, t) {
  const s = e.type;
  e.accessCache = /* @__PURE__ */ Object.create(null), e.proxy = new Proxy(e.ctx, $l);
  const { setup: n } = s;
  if (n) {
    ct();
    const r = e.setupContext = n.length > 1 ? lc(e) : null, i = hs(e), o = fs(
      n,
      e,
      0,
      [
        e.props,
        r
      ]
    ), l = zr(o);
    if (at(), i(), (l || e.sp) && !Xt(e) && Oi(e), l) {
      if (o.then(kr, kr), t)
        return o.then((c) => {
          Cr(e, c);
        }).catch((c) => {
          Ks(c, e, 0);
        });
      e.asyncDep = o;
    } else
      Cr(e, o);
  } else
    no(e);
}
function Cr(e, t, s) {
  te(t) ? e.type.__ssrInlineRender ? e.ssrRender = t : e.render = t : oe(t) && (e.setupState = vi(t)), no(e);
}
function no(e, t, s) {
  const n = e.type;
  e.render || (e.render = n.render || et);
  {
    const r = hs(e);
    ct();
    try {
      Tl(e);
    } finally {
      at(), r();
    }
  }
}
const oc = {
  get(e, t) {
    return be(e, "get", ""), e[t];
  }
};
function lc(e) {
  const t = (s) => {
    e.exposed = s || {};
  };
  return {
    attrs: new Proxy(e.attrs, oc),
    slots: e.slots,
    emit: e.emit,
    expose: t
  };
}
function Qs(e) {
  return e.exposed ? e.exposeProxy || (e.exposeProxy = new Proxy(vi(Jo(e.exposed)), {
    get(t, s) {
      if (s in t)
        return t[s];
      if (s in es)
        return es[s](e);
    },
    has(t, s) {
      return s in t || s in es;
    }
  })) : e.proxy;
}
function cc(e) {
  return te(e) && "__vccOpts" in e;
}
const ge = (e, t) => /* @__PURE__ */ Xo(e, t, ls);
function ro(e, t, s) {
  try {
    Es(-1);
    const n = arguments.length;
    return n === 2 ? oe(t) && !G(t) ? Is(t) ? ye(e, null, [t]) : ye(e, t) : ye(e, null, t) : (n > 3 ? s = Array.prototype.slice.call(arguments, 2) : n === 3 && Is(s) && (s = [s]), ye(e, t, s));
  } finally {
    Es(1);
  }
}
const ac = "3.5.30";
/**
* @vue/runtime-dom v3.5.30
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
let Pn;
const $r = typeof window < "u" && window.trustedTypes;
if ($r)
  try {
    Pn = /* @__PURE__ */ $r.createPolicy("vue", {
      createHTML: (e) => e
    });
  } catch {
  }
const io = Pn ? (e) => Pn.createHTML(e) : (e) => e, uc = "http://www.w3.org/2000/svg", fc = "http://www.w3.org/1998/Math/MathML", rt = typeof document < "u" ? document : null, Tr = rt && /* @__PURE__ */ rt.createElement("template"), dc = {
  insert: (e, t, s) => {
    t.insertBefore(e, s || null);
  },
  remove: (e) => {
    const t = e.parentNode;
    t && t.removeChild(e);
  },
  createElement: (e, t, s, n) => {
    const r = t === "svg" ? rt.createElementNS(uc, e) : t === "mathml" ? rt.createElementNS(fc, e) : s ? rt.createElement(e, { is: s }) : rt.createElement(e);
    return e === "select" && n && n.multiple != null && r.setAttribute("multiple", n.multiple), r;
  },
  createText: (e) => rt.createTextNode(e),
  createComment: (e) => rt.createComment(e),
  setText: (e, t) => {
    e.nodeValue = t;
  },
  setElementText: (e, t) => {
    e.textContent = t;
  },
  parentNode: (e) => e.parentNode,
  nextSibling: (e) => e.nextSibling,
  querySelector: (e) => rt.querySelector(e),
  setScopeId(e, t) {
    e.setAttribute(t, "");
  },
  // __UNSAFE__
  // Reason: innerHTML.
  // Static content here can only come from compiled templates.
  // As long as the user only uses trusted templates, this is safe.
  insertStaticContent(e, t, s, n, r, i) {
    const o = s ? s.previousSibling : t.lastChild;
    if (r && (r === i || r.nextSibling))
      for (; t.insertBefore(r.cloneNode(!0), s), !(r === i || !(r = r.nextSibling)); )
        ;
    else {
      Tr.innerHTML = io(
        n === "svg" ? `<svg>${e}</svg>` : n === "mathml" ? `<math>${e}</math>` : e
      );
      const l = Tr.content;
      if (n === "svg" || n === "mathml") {
        const c = l.firstChild;
        for (; c.firstChild; )
          l.appendChild(c.firstChild);
        l.removeChild(c);
      }
      t.insertBefore(l, s);
    }
    return [
      // first
      o ? o.nextSibling : t.firstChild,
      // last
      s ? s.previousSibling : t.lastChild
    ];
  }
}, dt = "transition", Ut = "animation", cs = /* @__PURE__ */ Symbol("_vtc"), oo = {
  name: String,
  type: String,
  css: {
    type: Boolean,
    default: !0
  },
  duration: [String, Number, Object],
  enterFromClass: String,
  enterActiveClass: String,
  enterToClass: String,
  appearFromClass: String,
  appearActiveClass: String,
  appearToClass: String,
  leaveFromClass: String,
  leaveActiveClass: String,
  leaveToClass: String
}, pc = /* @__PURE__ */ ve(
  {},
  Pi,
  oo
), hc = (e) => (e.displayName = "Transition", e.props = pc, e), Xs = /* @__PURE__ */ hc(
  (e, { slots: t }) => ro(pl, _c(e), t)
), yt = (e, t = []) => {
  G(e) ? e.forEach((s) => s(...t)) : e && e(...t);
}, Sr = (e) => e ? G(e) ? e.some((t) => t.length > 1) : e.length > 1 : !1;
function _c(e) {
  const t = {};
  for (const D in e)
    D in oo || (t[D] = e[D]);
  if (e.css === !1)
    return t;
  const {
    name: s = "v",
    type: n,
    duration: r,
    enterFromClass: i = `${s}-enter-from`,
    enterActiveClass: o = `${s}-enter-active`,
    enterToClass: l = `${s}-enter-to`,
    appearFromClass: c = i,
    appearActiveClass: u = o,
    appearToClass: a = l,
    leaveFromClass: d = `${s}-leave-from`,
    leaveActiveClass: y = `${s}-leave-active`,
    leaveToClass: $ = `${s}-leave-to`
  } = e, H = gc(r), O = H && H[0], Y = H && H[1], {
    onBeforeEnter: B,
    onEnter: E,
    onEnterCancelled: z,
    onLeave: x,
    onLeaveCancelled: V,
    onBeforeAppear: X = B,
    onAppear: ee = E,
    onAppearCancelled: se = z
  } = t, S = (D, M, R, b) => {
    D._enterCancelled = b, xt(D, M ? a : l), xt(D, M ? u : o), R && R();
  }, j = (D, M) => {
    D._isLeaving = !1, xt(D, d), xt(D, $), xt(D, y), M && M();
  }, q = (D) => (M, R) => {
    const b = D ? ee : E, W = () => S(M, D, R);
    yt(b, [M, W]), Mr(() => {
      xt(M, D ? c : i), nt(M, D ? a : l), Sr(b) || Pr(M, n, O, W);
    });
  };
  return ve(t, {
    onBeforeEnter(D) {
      yt(B, [D]), nt(D, i), nt(D, o);
    },
    onBeforeAppear(D) {
      yt(X, [D]), nt(D, c), nt(D, u);
    },
    onEnter: q(!1),
    onAppear: q(!0),
    onLeave(D, M) {
      D._isLeaving = !0;
      const R = () => j(D, M);
      nt(D, d), D._enterCancelled ? (nt(D, y), Er(D)) : (Er(D), nt(D, y)), Mr(() => {
        D._isLeaving && (xt(D, d), nt(D, $), Sr(x) || Pr(D, n, Y, R));
      }), yt(x, [D, R]);
    },
    onEnterCancelled(D) {
      S(D, !1, void 0, !0), yt(z, [D]);
    },
    onAppearCancelled(D) {
      S(D, !0, void 0, !0), yt(se, [D]);
    },
    onLeaveCancelled(D) {
      j(D), yt(V, [D]);
    }
  });
}
function gc(e) {
  if (e == null)
    return null;
  if (oe(e))
    return [fn(e.enter), fn(e.leave)];
  {
    const t = fn(e);
    return [t, t];
  }
}
function fn(e) {
  return yo(e);
}
function nt(e, t) {
  t.split(/\s+/).forEach((s) => s && e.classList.add(s)), (e[cs] || (e[cs] = /* @__PURE__ */ new Set())).add(t);
}
function xt(e, t) {
  t.split(/\s+/).forEach((n) => n && e.classList.remove(n));
  const s = e[cs];
  s && (s.delete(t), s.size || (e[cs] = void 0));
}
function Mr(e) {
  requestAnimationFrame(() => {
    requestAnimationFrame(e);
  });
}
let mc = 0;
function Pr(e, t, s, n) {
  const r = e._endId = ++mc, i = () => {
    r === e._endId && n();
  };
  if (s != null)
    return setTimeout(i, s);
  const { type: o, timeout: l, propCount: c } = vc(e, t);
  if (!o)
    return n();
  const u = o + "end";
  let a = 0;
  const d = () => {
    e.removeEventListener(u, y), i();
  }, y = ($) => {
    $.target === e && ++a >= c && d();
  };
  setTimeout(() => {
    a < c && d();
  }, l + 1), e.addEventListener(u, y);
}
function vc(e, t) {
  const s = window.getComputedStyle(e), n = (H) => (s[H] || "").split(", "), r = n(`${dt}Delay`), i = n(`${dt}Duration`), o = Lr(r, i), l = n(`${Ut}Delay`), c = n(`${Ut}Duration`), u = Lr(l, c);
  let a = null, d = 0, y = 0;
  t === dt ? o > 0 && (a = dt, d = o, y = i.length) : t === Ut ? u > 0 && (a = Ut, d = u, y = c.length) : (d = Math.max(o, u), a = d > 0 ? o > u ? dt : Ut : null, y = a ? a === dt ? i.length : c.length : 0);
  const $ = a === dt && /\b(?:transform|all)(?:,|$)/.test(
    n(`${dt}Property`).toString()
  );
  return {
    type: a,
    timeout: d,
    propCount: y,
    hasTransform: $
  };
}
function Lr(e, t) {
  for (; e.length < t.length; )
    e = e.concat(e);
  return Math.max(...t.map((s, n) => Ar(s) + Ar(e[n])));
}
function Ar(e) {
  return e === "auto" ? 0 : Number(e.slice(0, -1).replace(",", ".")) * 1e3;
}
function Er(e) {
  return (e ? e.ownerDocument : document).body.offsetHeight;
}
function yc(e, t, s) {
  const n = e[cs];
  n && (t = (t ? [t, ...n] : [...n]).join(" ")), t == null ? e.removeAttribute("class") : s ? e.setAttribute("class", t) : e.className = t;
}
const Hs = /* @__PURE__ */ Symbol("_vod"), lo = /* @__PURE__ */ Symbol("_vsh"), Ir = {
  // used for prop mismatch check during hydration
  name: "show",
  beforeMount(e, { value: t }, { transition: s }) {
    e[Hs] = e.style.display === "none" ? "" : e.style.display, s && t ? s.beforeEnter(e) : Kt(e, t);
  },
  mounted(e, { value: t }, { transition: s }) {
    s && t && s.enter(e);
  },
  updated(e, { value: t, oldValue: s }, { transition: n }) {
    !t != !s && (n ? t ? (n.beforeEnter(e), Kt(e, !0), n.enter(e)) : n.leave(e, () => {
      Kt(e, !1);
    }) : Kt(e, t));
  },
  beforeUnmount(e, { value: t }) {
    Kt(e, t);
  }
};
function Kt(e, t) {
  e.style.display = t ? e[Hs] : "none", e[lo] = !t;
}
const xc = /* @__PURE__ */ Symbol(""), bc = /(?:^|;)\s*display\s*:/;
function wc(e, t, s) {
  const n = e.style, r = _e(s);
  let i = !1;
  if (s && !r) {
    if (t)
      if (_e(t))
        for (const o of t.split(";")) {
          const l = o.slice(0, o.indexOf(":")).trim();
          s[l] == null && Ts(n, l, "");
        }
      else
        for (const o in t)
          s[o] == null && Ts(n, o, "");
    for (const o in s)
      o === "display" && (i = !0), Ts(n, o, s[o]);
  } else if (r) {
    if (t !== s) {
      const o = n[xc];
      o && (s += ";" + o), n.cssText = s, i = bc.test(s);
    }
  } else t && e.removeAttribute("style");
  Hs in e && (e[Hs] = i ? n.display : "", e[lo] && (n.display = "none"));
}
const Or = /\s*!important$/;
function Ts(e, t, s) {
  if (G(s))
    s.forEach((n) => Ts(e, t, n));
  else if (s == null && (s = ""), t.startsWith("--"))
    e.setProperty(t, s);
  else {
    const n = kc(e, t);
    Or.test(s) ? e.setProperty(
      Tt(n),
      s.replace(Or, ""),
      "important"
    ) : e[n] = s;
  }
}
const Hr = ["Webkit", "Moz", "ms"], dn = {};
function kc(e, t) {
  const s = dn[t];
  if (s)
    return s;
  let n = je(t);
  if (n !== "filter" && n in e)
    return dn[t] = n;
  n = Qr(n);
  for (let r = 0; r < Hr.length; r++) {
    const i = Hr[r] + n;
    if (i in e)
      return dn[t] = i;
  }
  return t;
}
const Fr = "http://www.w3.org/1999/xlink";
function Dr(e, t, s, n, r, i = $o(t)) {
  n && t.startsWith("xlink:") ? s == null ? e.removeAttributeNS(Fr, t.slice(6, t.length)) : e.setAttributeNS(Fr, t, s) : s == null || i && !ei(s) ? e.removeAttribute(t) : e.setAttribute(
    t,
    i ? "" : tt(s) ? String(s) : s
  );
}
function Rr(e, t, s, n, r) {
  if (t === "innerHTML" || t === "textContent") {
    s != null && (e[t] = t === "innerHTML" ? io(s) : s);
    return;
  }
  const i = e.tagName;
  if (t === "value" && i !== "PROGRESS" && // custom elements may use _value internally
  !i.includes("-")) {
    const l = i === "OPTION" ? e.getAttribute("value") || "" : e.value, c = s == null ? (
      // #11647: value should be set as empty string for null and undefined,
      // but <input type="checkbox"> should be set as 'on'.
      e.type === "checkbox" ? "on" : ""
    ) : String(s);
    (l !== c || !("_value" in e)) && (e.value = c), s == null && e.removeAttribute(t), e._value = s;
    return;
  }
  let o = !1;
  if (s === "" || s == null) {
    const l = typeof e[t];
    l === "boolean" ? s = ei(s) : s == null && l === "string" ? (s = "", o = !0) : l === "number" && (s = 0, o = !0);
  }
  try {
    e[t] = s;
  } catch {
  }
  o && e.removeAttribute(r || t);
}
function kt(e, t, s, n) {
  e.addEventListener(t, s, n);
}
function Cc(e, t, s, n) {
  e.removeEventListener(t, s, n);
}
const jr = /* @__PURE__ */ Symbol("_vei");
function $c(e, t, s, n, r = null) {
  const i = e[jr] || (e[jr] = {}), o = i[t];
  if (n && o)
    o.value = n;
  else {
    const [l, c] = Tc(t);
    if (n) {
      const u = i[t] = Pc(
        n,
        r
      );
      kt(e, l, u, c);
    } else o && (Cc(e, l, o, c), i[t] = void 0);
  }
}
const Nr = /(?:Once|Passive|Capture)$/;
function Tc(e) {
  let t;
  if (Nr.test(e)) {
    t = {};
    let n;
    for (; n = e.match(Nr); )
      e = e.slice(0, e.length - n[0].length), t[n[0].toLowerCase()] = !0;
  }
  return [e[2] === ":" ? e.slice(3) : Tt(e.slice(2)), t];
}
let pn = 0;
const Sc = /* @__PURE__ */ Promise.resolve(), Mc = () => pn || (Sc.then(() => pn = 0), pn = Date.now());
function Pc(e, t) {
  const s = (n) => {
    if (!n._vts)
      n._vts = Date.now();
    else if (n._vts <= s.attached)
      return;
    We(
      Lc(n, s.value),
      t,
      5,
      [n]
    );
  };
  return s.value = e, s.attached = Mc(), s;
}
function Lc(e, t) {
  if (G(t)) {
    const s = e.stopImmediatePropagation;
    return e.stopImmediatePropagation = () => {
      s.call(e), e._stopped = !0;
    }, t.map(
      (n) => (r) => !r._stopped && n && n(r)
    );
  } else
    return t;
}
const Vr = (e) => e.charCodeAt(0) === 111 && e.charCodeAt(1) === 110 && // lowercase letter
e.charCodeAt(2) > 96 && e.charCodeAt(2) < 123, Ac = (e, t, s, n, r, i) => {
  const o = r === "svg";
  t === "class" ? yc(e, n, o) : t === "style" ? wc(e, s, n) : js(t) ? Hn(t) || $c(e, t, s, n, i) : (t[0] === "." ? (t = t.slice(1), !0) : t[0] === "^" ? (t = t.slice(1), !1) : Ec(e, t, n, o)) ? (Rr(e, t, n), !e.tagName.includes("-") && (t === "value" || t === "checked" || t === "selected") && Dr(e, t, n, o, i, t !== "value")) : /* #11081 force set props for possible async custom element */ e._isVueCE && // #12408 check if it's declared prop or it's async custom element
  (Ic(e, t) || // @ts-expect-error _def is private
  e._def.__asyncLoader && (/[A-Z]/.test(t) || !_e(n))) ? Rr(e, je(t), n, i, t) : (t === "true-value" ? e._trueValue = n : t === "false-value" && (e._falseValue = n), Dr(e, t, n, o));
};
function Ec(e, t, s, n) {
  if (n)
    return !!(t === "innerHTML" || t === "textContent" || t in e && Vr(t) && te(s));
  if (t === "spellcheck" || t === "draggable" || t === "translate" || t === "autocorrect" || t === "sandbox" && e.tagName === "IFRAME" || t === "form" || t === "list" && e.tagName === "INPUT" || t === "type" && e.tagName === "TEXTAREA")
    return !1;
  if (t === "width" || t === "height") {
    const r = e.tagName;
    if (r === "IMG" || r === "VIDEO" || r === "CANVAS" || r === "SOURCE")
      return !1;
  }
  return Vr(t) && _e(s) ? !1 : t in e;
}
function Ic(e, t) {
  const s = (
    // @ts-expect-error _def is private
    e._def.props
  );
  if (!s)
    return !1;
  const n = je(t);
  return Array.isArray(s) ? s.some((r) => je(r) === n) : Object.keys(s).some((r) => je(r) === n);
}
const Fs = (e) => {
  const t = e.props["onUpdate:modelValue"] || !1;
  return G(t) ? (s) => ws(t, s) : t;
};
function Oc(e) {
  e.target.composing = !0;
}
function Wr(e) {
  const t = e.target;
  t.composing && (t.composing = !1, t.dispatchEvent(new Event("input")));
}
const Dt = /* @__PURE__ */ Symbol("_assign");
function Br(e, t, s) {
  return t && (e = e.trim()), s && (e = Rn(e)), e;
}
const Ur = {
  created(e, { modifiers: { lazy: t, trim: s, number: n } }, r) {
    e[Dt] = Fs(r);
    const i = n || r.props && r.props.type === "number";
    kt(e, t ? "change" : "input", (o) => {
      o.target.composing || e[Dt](Br(e.value, s, i));
    }), (s || i) && kt(e, "change", () => {
      e.value = Br(e.value, s, i);
    }), t || (kt(e, "compositionstart", Oc), kt(e, "compositionend", Wr), kt(e, "change", Wr));
  },
  // set value on mounted so it's after min/max for type="range"
  mounted(e, { value: t }) {
    e.value = t ?? "";
  },
  beforeUpdate(e, { value: t, oldValue: s, modifiers: { lazy: n, trim: r, number: i } }, o) {
    if (e[Dt] = Fs(o), e.composing) return;
    const l = (i || e.type === "number") && !/^0\d/.test(e.value) ? Rn(e.value) : e.value, c = t ?? "";
    l !== c && (document.activeElement === e && e.type !== "range" && (n && t === s || r && e.value.trim() === c) || (e.value = c));
  }
}, Hc = {
  // #4096 array checkboxes need to be deep traversed
  deep: !0,
  created(e, t, s) {
    e[Dt] = Fs(s), kt(e, "change", () => {
      const n = e._modelValue, r = Fc(e), i = e.checked, o = e[Dt];
      if (G(n)) {
        const l = ti(n, r), c = l !== -1;
        if (i && !c)
          o(n.concat(r));
        else if (!i && c) {
          const u = [...n];
          u.splice(l, 1), o(u);
        }
      } else if (Ns(n)) {
        const l = new Set(n);
        i ? l.add(r) : l.delete(r), o(l);
      } else
        o(co(e, i));
    });
  },
  // set initial checked on mount to wait for true-value/false-value
  mounted: Kr,
  beforeUpdate(e, t, s) {
    e[Dt] = Fs(s), Kr(e, t, s);
  }
};
function Kr(e, { value: t, oldValue: s }, n) {
  e._modelValue = t;
  let r;
  if (G(t))
    r = ti(t, n.props.value) > -1;
  else if (Ns(t))
    r = t.has(n.props.value);
  else {
    if (t === s) return;
    r = us(t, co(e, !0));
  }
  e.checked !== r && (e.checked = r);
}
function Fc(e) {
  return "_value" in e ? e._value : e.value;
}
function co(e, t) {
  const s = t ? "_trueValue" : "_falseValue";
  return s in e ? e[s] : t;
}
const Dc = ["ctrl", "shift", "alt", "meta"], Rc = {
  stop: (e) => e.stopPropagation(),
  prevent: (e) => e.preventDefault(),
  self: (e) => e.target !== e.currentTarget,
  ctrl: (e) => !e.ctrlKey,
  shift: (e) => !e.shiftKey,
  alt: (e) => !e.altKey,
  meta: (e) => !e.metaKey,
  left: (e) => "button" in e && e.button !== 0,
  middle: (e) => "button" in e && e.button !== 1,
  right: (e) => "button" in e && e.button !== 2,
  exact: (e, t) => Dc.some((s) => e[`${s}Key`] && !t.includes(s))
}, ao = (e, t) => {
  if (!e) return e;
  const s = e._withMods || (e._withMods = {}), n = t.join(".");
  return s[n] || (s[n] = ((r, ...i) => {
    for (let o = 0; o < t.length; o++) {
      const l = Rc[t[o]];
      if (l && l(r, t)) return;
    }
    return e(r, ...i);
  }));
}, jc = /* @__PURE__ */ ve({ patchProp: Ac }, dc);
let Gr;
function Nc() {
  return Gr || (Gr = Gl(jc));
}
const Vc = ((...e) => {
  const t = Nc().createApp(...e), { mount: s } = t;
  return t.mount = (n) => {
    const r = Bc(n);
    if (!r) return;
    const i = t._component;
    !te(i) && !i.render && !i.template && (i.template = r.innerHTML), r.nodeType === 1 && (r.textContent = "");
    const o = s(r, !1, Wc(r));
    return r instanceof Element && (r.removeAttribute("v-cloak"), r.setAttribute("data-v-app", "")), o;
  }, t;
});
function Wc(e) {
  if (e instanceof SVGElement)
    return "svg";
  if (typeof MathMLElement == "function" && e instanceof MathMLElement)
    return "mathml";
}
function Bc(e) {
  return _e(e) ? document.querySelector(e) : e;
}
let Ds = "/api/v1", Ln = null, uo = 15e3;
const Rs = /* @__PURE__ */ new Map(), Uc = 6e4;
let Qn = !0;
function Pt(e) {
  if (!Qn) return null;
  const t = Rs.get(e);
  return t ? Date.now() - t.ts > Uc ? (Rs.delete(e), null) : t.data : null;
}
function Lt(e, t) {
  Qn && Rs.set(e, { data: t, ts: Date.now() });
}
function Kc() {
  Rs.clear();
}
function Gc({ baseUrl: e, token: t, timeout: s, cache: n }) {
  {
    let r = e.replace(/\/+$/, "");
    typeof window < "u" && window.location.protocol === "https:" && r.startsWith("http://") && (r = r.replace(/^http:\/\//, "https://")), Ds = r;
  }
  t && (Ln = t), s && (uo = s), n === !1 && (Qn = !1);
}
function fo(e) {
  if (!e) return null;
  if (e.startsWith("http://") || e.startsWith("https://"))
    return typeof window < "u" && window.location.protocol === "https:" && e.startsWith("http://") ? e.replace(/^http:\/\//, "https://") : e;
  if (Ds.startsWith("http"))
    try {
      return new URL(Ds).origin + e;
    } catch {
    }
  return e;
}
async function Je(e, t = {}) {
  const s = Ds + e, n = {
    Accept: "application/json",
    "Content-Type": "application/json"
  };
  Ln && (n.Authorization = `Bearer ${Ln}`);
  const r = new AbortController(), i = setTimeout(() => r.abort(), uo);
  try {
    const o = await fetch(s, {
      ...t,
      headers: { ...n, ...t.headers },
      signal: r.signal
    });
    if (clearTimeout(i), !o.ok) {
      const l = new Error(`HTTP ${o.status}`);
      l.status = o.status;
      try {
        l.data = await o.json();
      } catch {
      }
      throw l;
    }
    return o;
  } catch (o) {
    throw clearTimeout(i), o;
  }
}
function At(e = {}) {
  const t = new URLSearchParams();
  if (e.page && t.set("page", e.page), e.perPage && t.set("per_page", e.perPage), e.sort && t.set("sort", e.sort), e.order && t.set("order", e.order), e.search && t.set("search", e.search), e.category && t.set("category", e.category), e.hierarchyType && t.set("hierarchy_type", e.hierarchyType), e.lang && t.set("lang", e.lang), e.type && t.set("type", e.type), e.hierarchyId && t.set("hierarchy_id", e.hierarchyId), e.filters)
    for (const [n, r] of Object.entries(e.filters))
      t.set(`filters[${n}]`, r);
  const s = t.toString();
  return s ? `?${s}` : "";
}
const qc = {
  async getProducts(e = {}) {
    const t = `/catalog/products${At(e)}`, s = Pt(t);
    if (s) return s;
    const n = await Je(t), r = await n.json(), i = {
      products: Array.isArray(r) ? r : r.data || r,
      meta: {
        current_page: parseInt(n.headers.get("x-current-page") || "1", 10),
        last_page: parseInt(n.headers.get("x-last-page") || "1", 10),
        per_page: parseInt(n.headers.get("x-per-page") || "24", 10),
        total: parseInt(n.headers.get("x-total-count") || "0", 10)
      }
    };
    return Lt(t, i), i;
  },
  async getProduct(e, t = {}) {
    const s = `/catalog/products/${e}${At(t)}`, n = Pt(s);
    if (n) return n;
    const i = await (await Je(s)).json(), o = i.data || i;
    return Lt(s, o), o;
  },
  async getCategories(e = {}) {
    const t = `/catalog/categories${At(e)}`, s = Pt(t);
    if (s) return s;
    const r = await (await Je(t)).json(), i = r.data || r;
    return Lt(t, i), i;
  },
  async getSettings() {
    const e = "/catalog/settings", t = Pt(e);
    if (t) return t;
    const n = await (await Je(e)).json(), r = n.data || n;
    return Lt(e, r), r;
  },
  async getFacets(e = {}) {
    const t = `/catalog/facets${At(e)}`, s = Pt(t);
    if (s) return s;
    const r = await (await Je(t)).json();
    return Lt(t, r), r;
  },
  async getAttributeGroups(e = {}) {
    const t = `/catalog/attribute-groups${At(e)}`, s = Pt(t);
    if (s) return s;
    const r = await (await Je(t)).json();
    return Lt(t, r), r;
  },
  async downloadProductPdf(e, t = {}) {
    return (await Je(`/catalog/products/${e}/pdf${At(t)}`)).blob();
  },
  async downloadWishlistPdf(e, t) {
    return (await Je("/catalog/wishlist/pdf", {
      method: "POST",
      body: JSON.stringify({ product_ids: e, lang: t })
    })).blob();
  },
  async downloadWishlistExcel(e) {
    return (await Je("/catalog/wishlist/excel", {
      method: "POST",
      body: JSON.stringify({ product_ids: e })
    })).blob();
  },
  async compareProducts(e, t) {
    const n = await (await Je("/catalog/products/compare", {
      method: "POST",
      body: JSON.stringify({ product_ids: e, lang: t })
    })).json();
    return n.data || n;
  }
};
let Re = qc, qr = fo;
function Jc() {
  const e = /* @__PURE__ */ Us({
    // Products
    products: [],
    currentProduct: null,
    loading: !1,
    productLoading: !1,
    error: null,
    // Pagination
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 24,
      total: 0
    },
    // Filters & navigation
    search: "",
    selectedCategoryId: null,
    selectedCategoryName: null,
    sort: { field: "name", order: "asc" },
    viewMode: typeof localStorage < "u" && localStorage.getItem("pxc_view_mode") || "grid",
    locale: typeof localStorage < "u" && localStorage.getItem("pxc_locale") || "de",
    // Categories
    categories: [],
    hierarchyInfo: null,
    categoriesLoading: !1,
    // Facets
    facets: [],
    activeFilters: {},
    // Attribute groups (for detail view grouping)
    attributeGroups: [],
    // Wishlist
    wishlistIds: JSON.parse(typeof localStorage < "u" && localStorage.getItem("pxc_wishlist") || "[]"),
    // Settings (from PIM)
    settings: {},
    _settingsLoaded: !1,
    // Compare
    compareData: null,
    compareLoading: !1,
    compareOpen: !1,
    compareProductIds: [],
    // Product detail modal/view
    detailOpen: !1,
    detailProductId: null,
    // Mobile sidebar
    sidebarOpen: !1
  });
  typeof localStorage < "u" && (Ae(() => e.wishlistIds, (n) => {
    localStorage.setItem("pxc_wishlist", JSON.stringify(n));
  }, { deep: !0 }), Ae(() => e.viewMode, (n) => localStorage.setItem("pxc_view_mode", n)), Ae(() => e.locale, (n) => localStorage.setItem("pxc_locale", n)));
  const t = {
    isEmpty: ge(() => e.products.length === 0 && !e.loading),
    wishlistCount: ge(() => e.wishlistIds.length),
    searchActive: ge(() => e.search && e.search.trim().length > 0),
    activeFilterCount: ge(() => Object.keys(e.activeFilters).length),
    isInWishlist(n) {
      return e.wishlistIds.includes(n);
    }
  }, s = {
    async fetchSettings() {
      try {
        const n = await Re.getSettings();
        e.settings = n || {}, !(typeof localStorage < "u" && localStorage.getItem("pxc_locale")) && n.default_locale && (e.locale = n.default_locale), e._settingsLoaded = !0;
      } catch (n) {
        console.warn("[PublixxCatalog] Failed to load settings:", n.message);
      }
    },
    async fetchProducts() {
      var n;
      e.loading = !0, e.error = null;
      try {
        const r = e.search && e.search.trim().length > 0, i = await Re.getProducts({
          page: e.meta.current_page,
          perPage: e.meta.per_page,
          sort: e.sort.field,
          order: e.sort.order,
          search: e.search || void 0,
          category: r ? void 0 : e.selectedCategoryId || void 0,
          lang: e.locale,
          filters: r ? void 0 : Object.keys(e.activeFilters).length > 0 ? { ...e.activeFilters } : void 0,
          hierarchyId: e.settings.hierarchy_id || void 0
        });
        e.products = i.products.map((o) => ({
          ...o,
          image_url: qr(o.image_url)
        })), e.meta = i.meta;
      } catch (r) {
        e.error = ((n = r.data) == null ? void 0 : n.title) || "Fehler beim Laden", e.products = [];
      } finally {
        e.loading = !1;
      }
    },
    async fetchProduct(n) {
      var r;
      e.productLoading = !0, e.error = null;
      try {
        const i = await Re.getProduct(n, { lang: e.locale });
        i != null && i.media && (i.media = i.media.map((o) => ({ ...o, url: qr(o.url) }))), e.currentProduct = i;
      } catch (i) {
        e.error = ((r = i.data) == null ? void 0 : r.title) || "Produkt nicht gefunden", e.currentProduct = null;
      } finally {
        e.productLoading = !1;
      }
    },
    async fetchCategories() {
      e.categoriesLoading = !0;
      try {
        const n = await Re.getCategories({
          lang: e.locale,
          hierarchyId: e.settings.hierarchy_id || void 0
        });
        e.categories = n.nodes || [], e.hierarchyInfo = {
          hierarchy_id: n.hierarchy_id,
          hierarchy_name: n.hierarchy_name,
          type: n.type
        };
      } catch (n) {
        console.error("[PublixxCatalog] Categories load failed:", n), e.categories = [];
      } finally {
        e.categoriesLoading = !1;
      }
    },
    async fetchFacets() {
      try {
        const n = await Re.getFacets({ lang: e.locale });
        e.facets = n.facets || [];
      } catch (n) {
        console.warn("[PublixxCatalog] Facets load failed:", n.message), e.facets = [];
      }
    },
    async fetchAttributeGroups() {
      try {
        const n = await Re.getAttributeGroups({ lang: e.locale });
        e.attributeGroups = n.data || [];
      } catch (n) {
        console.warn("[PublixxCatalog] Attribute groups load failed:", n.message), e.attributeGroups = [];
      }
    },
    // Navigation
    setSearch(n) {
      e.search = n, e.meta.current_page = 1;
    },
    setCategory(n, r = null) {
      e.selectedCategoryId = n, e.selectedCategoryName = r, e.meta.current_page = 1;
    },
    clearCategory() {
      e.selectedCategoryId = null, e.selectedCategoryName = null, e.meta.current_page = 1;
    },
    setPage(n) {
      e.meta.current_page = n;
    },
    setSort(n, r) {
      e.sort = { field: n, order: r }, e.meta.current_page = 1;
    },
    setViewMode(n) {
      e.viewMode = n;
    },
    setLocale(n) {
      e.locale = n, Kc();
    },
    // Filters
    setFilter(n, r) {
      e.activeFilters[n] = r, e.meta.current_page = 1;
    },
    clearFilter(n) {
      delete e.activeFilters[n], e.meta.current_page = 1;
    },
    clearAllFilters() {
      for (const n of Object.keys(e.activeFilters))
        delete e.activeFilters[n];
      e.meta.current_page = 1;
    },
    // Wishlist
    toggleWishlist(n) {
      const r = e.wishlistIds.indexOf(n);
      r === -1 ? e.wishlistIds.push(n) : e.wishlistIds.splice(r, 1);
    },
    clearWishlist() {
      e.wishlistIds.splice(0, e.wishlistIds.length);
    },
    /**
     * Process URL deeplinks: ?sku=XXX opens product detail, ?cat=XXX selects category.
     */
    async applyDeeplinks() {
      const n = new URLSearchParams(window.location.search), r = n.get("cat");
      r && (s.setCategory(r), await s.fetchProducts());
      const i = n.get("sku");
      if (i)
        try {
          const o = await Re.getProducts({ search: i, perPage: 1, lang: e.locale }), l = o.products && o.products[0];
          l && s.openDetail(l.id);
        } catch (o) {
          console.warn("[PublixxCatalog] Deeplink SKU lookup failed:", o.message);
        }
    },
    importWishlistFromUrl() {
      const n = new URLSearchParams(window.location.search), r = n.get("wishlist");
      if (!r) return;
      const i = r.split(",").filter(Boolean), o = new Set(e.wishlistIds);
      for (const c of i)
        o.has(c) || e.wishlistIds.push(c);
      n.delete("wishlist");
      const l = n.toString() ? `${window.location.pathname}?${n.toString()}` : window.location.pathname;
      window.history.replaceState({}, "", l);
    },
    // Detail view
    openDetail(n) {
      e.detailProductId = n, e.detailOpen = !0, s.fetchProduct(n);
    },
    closeDetail() {
      e.detailOpen = !1, e.currentProduct = null, e.detailProductId = null;
    },
    // Compare
    async openCompare(n) {
      e.compareProductIds = n || [...e.wishlistIds], e.compareOpen = !0, e.compareLoading = !0;
      try {
        e.compareData = await Re.compareProducts(e.compareProductIds, e.locale);
      } catch (r) {
        console.error("[PublixxCatalog] Compare failed:", r), e.compareData = null;
      } finally {
        e.compareLoading = !1;
      }
    },
    closeCompare() {
      e.compareOpen = !1, e.compareData = null, e.compareProductIds = [];
    },
    // Mobile sidebar
    openSidebar() {
      e.sidebarOpen = !0;
    },
    closeSidebar() {
      e.sidebarOpen = !1;
    },
    toggleSidebar() {
      e.sidebarOpen = !e.sidebarOpen;
    },
    // Exports
    async downloadProductPdf(n) {
      const r = await Re.downloadProductPdf(n, { lang: e.locale });
      hn(r, `product-${n}.pdf`);
    },
    async downloadWishlistPdf() {
      const n = await Re.downloadWishlistPdf(e.wishlistIds, e.locale);
      hn(n, `wishlist-${(/* @__PURE__ */ new Date()).toISOString().slice(0, 10)}.pdf`);
    },
    async downloadWishlistExcel() {
      const n = await Re.downloadWishlistExcel(e.wishlistIds);
      hn(n, `wishlist-${(/* @__PURE__ */ new Date()).toISOString().slice(0, 10)}.xlsx`);
    }
  };
  return { state: e, getters: t, actions: s };
}
function hn(e, t) {
  const s = URL.createObjectURL(e), n = document.createElement("a");
  n.href = s, n.download = t, document.body.appendChild(n), n.click(), n.remove(), URL.revokeObjectURL(s);
}
let _n = null;
function Se() {
  return _n || (_n = Jc()), _n;
}
const U = {
  search: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
  x: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
  heart: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
  heartFilled: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
  chevronLeft: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>',
  chevronRight: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>',
  chevronDown: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>',
  grid: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>',
  list: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></svg>',
  fileDown: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>',
  sheet: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><line x1="3" x2="21" y1="9" y2="9"/><line x1="3" x2="21" y1="15" y2="15"/><line x1="9" x2="9" y1="9" y2="21"/><line x1="15" x2="15" y1="9" y2="21"/></svg>',
  compare: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><path d="M13 6h3a2 2 0 0 1 2 2v7"/><path d="M11 18H8a2 2 0 0 1-2-2V9"/></svg>',
  share: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"/><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"/></svg>',
  trash: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>',
  check: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
  package: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>',
  folder: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>',
  sortAsc: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 8 4-4 4 4"/><path d="M7 4v16"/><path d="M11 12h4"/><path d="M11 16h7"/><path d="M11 20h10"/></svg>',
  sortDesc: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 16 4 4 4-4"/><path d="M7 20V4"/><path d="M11 4h10"/><path d="M11 8h7"/><path d="M11 12h4"/></svg>',
  globe: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>',
  eye: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>',
  loader: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pxc-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>',
  filter: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>',
  close: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
  menu: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>'
}, zc = { class: "pxc-search__wrapper" }, Zc = ["innerHTML"], Yc = ["value"], Qc = ["innerHTML"], Xc = ["innerHTML"], ea = {
  __name: "SearchWidget",
  setup(e) {
    const { state: t, actions: s } = Se(), n = /* @__PURE__ */ He(t.search);
    let r = null;
    function i(c) {
      n.value = c.target.value, clearTimeout(r), r = setTimeout(() => {
        s.setSearch(n.value), s.fetchProducts();
      }, 300);
    }
    function o() {
      n.value = "", s.setSearch(""), s.fetchProducts();
    }
    function l(c) {
      c.preventDefault(), clearTimeout(r), s.setSearch(n.value), s.fetchProducts();
    }
    return (c, u) => (_(), m("form", {
      class: "pxc-search",
      onSubmit: l
    }, [
      h("div", zc, [
        h("span", {
          class: "pxc-search__icon",
          innerHTML: g(U).search
        }, null, 8, Zc),
        h("input", {
          type: "text",
          class: "pxc-search__input",
          value: n.value,
          placeholder: "Produkte suchen...",
          onInput: i
        }, null, 40, Yc),
        n.value ? (_(), m("button", {
          key: 0,
          type: "button",
          class: "pxc-search__clear",
          onClick: o,
          innerHTML: g(U).x
        }, null, 8, Qc)) : N("", !0),
        g(t).loading ? (_(), m("span", {
          key: 1,
          class: "pxc-search__loader",
          innerHTML: g(U).loader
        }, null, 8, Xc)) : N("", !0)
      ])
    ], 32));
  }
}, ta = { class: "pxc-categories" }, sa = { class: "pxc-categories__header" }, na = ["innerHTML"], ra = { class: "pxc-categories__count" }, ia = {
  key: 0,
  class: "pxc-categories__loading"
}, oa = { class: "pxc-categories__row" }, la = ["onClick", "innerHTML"], ca = {
  key: 1,
  class: "pxc-categories__toggle-space"
}, aa = ["onClick"], ua = {
  key: 0,
  class: "pxc-categories__count"
}, fa = { class: "pxc-categories__row" }, da = ["onClick", "innerHTML"], pa = {
  key: 1,
  class: "pxc-categories__toggle-space"
}, ha = ["onClick"], _a = {
  key: 0,
  class: "pxc-categories__count"
}, ga = { class: "pxc-categories__row" }, ma = ["onClick"], va = {
  key: 0,
  class: "pxc-categories__count"
}, ya = {
  __name: "CategoriesWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Se(), r = /* @__PURE__ */ He({});
    St(() => {
      t.categories.length === 0 && s.fetchCategories();
    }), Ae(() => t.locale, () => s.fetchCategories());
    function i(u) {
      s.setCategory(u.id, u.name), s.fetchProducts();
    }
    function o() {
      s.clearCategory(), s.fetchProducts();
    }
    function l(u) {
      r.value[u] = !r.value[u];
    }
    function c(u) {
      return !!r.value[u];
    }
    return (u, a) => (_(), m("div", ta, [
      h("div", sa, [
        h("span", {
          innerHTML: g(U).folder
        }, null, 8, na),
        a[0] || (a[0] = h("span", null, "Kategorien", -1))
      ]),
      h("button", {
        class: he(["pxc-categories__item", { "pxc-categories__item--active": !g(t).selectedCategoryId }]),
        onClick: o
      }, [
        a[1] || (a[1] = me(" Alle Kategorien ", -1)),
        h("span", ra, A(g(t).meta.total), 1)
      ], 2),
      g(t).categoriesLoading ? (_(), m("div", ia, [
        (_(), m(Z, null, ce(5, (d) => h("div", {
          key: d,
          class: "pxc-skeleton",
          style: { height: "24px", "margin-bottom": "4px" }
        })), 64))
      ])) : (_(!0), m(Z, { key: 1 }, ce(g(t).categories, (d) => (_(), m("div", {
        key: d.id,
        class: "pxc-categories__node"
      }, [
        h("div", oa, [
          d.children && d.children.length ? (_(), m("button", {
            key: 0,
            class: "pxc-categories__toggle",
            onClick: (y) => l(d.id),
            innerHTML: c(d.id) ? g(U).chevronDown : g(U).chevronRight
          }, null, 8, la)) : (_(), m("span", ca)),
          h("button", {
            class: he(["pxc-categories__item", { "pxc-categories__item--active": g(t).selectedCategoryId === d.id }]),
            onClick: (y) => i(d)
          }, [
            me(A(d.name) + " ", 1),
            d.product_count ? (_(), m("span", ua, A(d.product_count), 1)) : N("", !0)
          ], 10, aa)
        ]),
        c(d.id) && d.children ? (_(!0), m(Z, { key: 0 }, ce(d.children, (y) => (_(), m("div", {
          key: y.id,
          class: "pxc-categories__node pxc-categories__node--l1"
        }, [
          h("div", fa, [
            y.children && y.children.length ? (_(), m("button", {
              key: 0,
              class: "pxc-categories__toggle",
              onClick: ($) => l(y.id),
              innerHTML: c(y.id) ? g(U).chevronDown : g(U).chevronRight
            }, null, 8, da)) : (_(), m("span", pa)),
            h("button", {
              class: he(["pxc-categories__item", { "pxc-categories__item--active": g(t).selectedCategoryId === y.id }]),
              onClick: ($) => i(y)
            }, [
              me(A(y.name) + " ", 1),
              y.product_count ? (_(), m("span", _a, A(y.product_count), 1)) : N("", !0)
            ], 10, ha)
          ]),
          c(y.id) && y.children ? (_(!0), m(Z, { key: 0 }, ce(y.children, ($) => (_(), m("div", {
            key: $.id,
            class: "pxc-categories__node pxc-categories__node--l2"
          }, [
            h("div", ga, [
              a[2] || (a[2] = h("span", { class: "pxc-categories__toggle-space" }, null, -1)),
              h("button", {
                class: he(["pxc-categories__item", { "pxc-categories__item--active": g(t).selectedCategoryId === $.id }]),
                onClick: (H) => i($)
              }, [
                me(A($.name) + " ", 1),
                $.product_count ? (_(), m("span", va, A($.product_count), 1)) : N("", !0)
              ], 10, ma)
            ])
          ]))), 128)) : N("", !0)
        ]))), 128)) : N("", !0)
      ]))), 128))
    ]));
  }
}, xa = ["innerHTML"], ba = {
  key: 0,
  class: "pxc-facets-trigger__badge"
}, wa = { class: "pxc-facets-drawer__header" }, ka = ["innerHTML"], Ca = { class: "pxc-facets-drawer__body" }, $a = {
  key: 0,
  class: "pxc-facets"
}, Ta = ["onClick"], Sa = ["innerHTML"], Ma = { class: "pxc-facets__group-label" }, Pa = {
  key: 0,
  class: "pxc-facets__badge"
}, La = { class: "pxc-facets__body" }, Aa = {
  key: 0,
  class: "pxc-facets__search"
}, Ea = ["onUpdate:modelValue"], Ia = ["checked", "onChange"], Oa = { class: "pxc-facets__checkbox-label" }, Ha = { class: "pxc-facets__checkbox-count" }, Fa = ["onClick"], Da = ["onClick"], Ra = {
  key: 1,
  class: "pxc-facets__toggle"
}, ja = ["checked", "onChange"], Na = {
  key: 2,
  class: "pxc-facets__range"
}, Va = { class: "pxc-facets__range-field" }, Wa = ["placeholder", "value", "onChange"], Ba = { class: "pxc-facets__range-field" }, Ua = ["placeholder", "value", "onChange"], Ka = {
  key: 0,
  class: "pxc-facets__range-unit"
}, Ga = { class: "pxc-facets-drawer__footer" }, qa = {
  key: 1,
  class: "pxc-facets"
}, Ja = { class: "pxc-facets__header" }, za = ["onClick"], Za = ["innerHTML"], Ya = { class: "pxc-facets__group-label" }, Qa = {
  key: 0,
  class: "pxc-facets__badge"
}, Xa = { class: "pxc-facets__body" }, eu = {
  key: 0,
  class: "pxc-facets__search"
}, tu = ["onUpdate:modelValue"], su = ["checked", "onChange"], nu = { class: "pxc-facets__checkbox-label" }, ru = { class: "pxc-facets__checkbox-count" }, iu = ["onClick"], ou = ["onClick"], lu = {
  key: 1,
  class: "pxc-facets__toggle"
}, cu = ["checked", "onChange"], au = {
  key: 2,
  class: "pxc-facets__range"
}, uu = { class: "pxc-facets__range-field" }, fu = ["placeholder", "value", "onChange"], du = { class: "pxc-facets__range-field" }, pu = ["placeholder", "value", "onChange"], hu = {
  key: 0,
  class: "pxc-facets__range-unit"
}, bs = 5, _u = {
  __name: "FacetsWidget",
  props: {
    mode: { type: String, default: "inline" }
    // 'inline' or 'drawer'
  },
  setup(e) {
    const t = e, { state: s, actions: n, getters: r } = Se(), i = /* @__PURE__ */ He({}), o = /* @__PURE__ */ He({}), l = /* @__PURE__ */ He({}), c = /* @__PURE__ */ He(!1);
    function u() {
      c.value = !0;
    }
    function a() {
      c.value = !1;
    }
    function d() {
      c.value = !1;
    }
    St(() => {
      s.facets.length === 0 && n.fetchFacets();
    }), Ae(() => s.locale, () => n.fetchFacets());
    function y(M) {
      i.value[M] = !i.value[M];
    }
    function $(M) {
      return !!i.value[M];
    }
    function H(M) {
      o.value[M] = !o.value[M];
    }
    function O(M) {
      return !!o.value[M];
    }
    function Y(M) {
      const R = s.activeFilters[M];
      return R ? String(R).split(",").filter(Boolean) : [];
    }
    function B(M, R) {
      const b = Y(M), W = b.indexOf(String(R));
      W === -1 ? b.push(String(R)) : b.splice(W, 1), b.length === 0 ? n.clearFilter(M) : n.setFilter(M, b.join(",")), n.fetchProducts();
    }
    function E(M, R) {
      return Y(M).includes(String(R));
    }
    function z(M) {
      s.activeFilters[M] === "1" ? n.clearFilter(M) : n.setFilter(M, "1"), n.fetchProducts();
    }
    function x(M) {
      const R = s.activeFilters[M];
      if (!R) return { min: "", max: "" };
      const b = String(R).split(":");
      return { min: b[0] || "", max: b[1] || "" };
    }
    function V(M, R) {
      !R.min && !R.max ? n.clearFilter(M) : n.setFilter(M, `${R.min}:${R.max}`), n.fetchProducts();
    }
    function X(M, R) {
      V(M, { ...x(M), min: R });
    }
    function ee(M, R) {
      V(M, { ...x(M), max: R });
    }
    function se(M) {
      const R = M.values || [], b = (l.value[M.attribute_id] || "").toLowerCase();
      return b ? R.filter((W) => W.value.toLowerCase().includes(b)) : R;
    }
    function S(M) {
      const R = se(M);
      return O(M.attribute_id) ? R : R.slice(0, bs);
    }
    function j(M) {
      return se(M).length - bs;
    }
    function q(M) {
      return Y(M).length;
    }
    function D() {
      n.clearAllFilters(), n.fetchProducts();
    }
    return (M, R) => t.mode === "drawer" ? (_(), m(Z, { key: 0 }, [
      g(s).facets.length ? (_(), m("button", {
        key: 0,
        class: "pxc-facets-trigger",
        onClick: u
      }, [
        h("span", {
          innerHTML: g(U).filter || g(U).search
        }, null, 8, xa),
        R[1] || (R[1] = h("span", null, "Filtern", -1)),
        g(r).activeFilterCount.value > 0 ? (_(), m("span", ba, A(g(r).activeFilterCount.value), 1)) : N("", !0)
      ])) : N("", !0),
      (_(), ps(Gs, { to: "body" }, [
        ye(Xs, { name: "pxc-fade" }, {
          default: ds(() => [
            c.value ? (_(), m("div", {
              key: 0,
              class: "pxc-facets-drawer__overlay",
              onClick: a
            })) : N("", !0)
          ]),
          _: 1
        }),
        h("div", {
          class: he(["pxc-facets-drawer", { "pxc-facets-drawer--open": c.value }])
        }, [
          h("div", wa, [
            R[2] || (R[2] = h("span", { class: "pxc-facets-drawer__title" }, "Produktfilter", -1)),
            h("button", {
              class: "pxc-facets-drawer__close",
              onClick: a
            }, [
              h("span", {
                innerHTML: g(U).close
              }, null, 8, ka)
            ])
          ]),
          h("div", Ca, [
            g(s).facets.length ? (_(), m("div", $a, [
              (_(!0), m(Z, null, ce(g(s).facets, (b) => (_(), m("div", {
                key: b.attribute_id,
                class: "pxc-facets__group"
              }, [
                h("button", {
                  class: "pxc-facets__group-header",
                  onClick: (W) => y(b.attribute_id)
                }, [
                  h("span", {
                    innerHTML: $(b.attribute_id) ? g(U).chevronRight : g(U).chevronDown
                  }, null, 8, Sa),
                  h("span", Ma, A(b.label), 1),
                  q(b.attribute_id) > 0 ? (_(), m("span", Pa, A(q(b.attribute_id)), 1)) : N("", !0)
                ], 8, Ta),
                Gt(h("div", La, [
                  b.data_type === "ValueList" || b.data_type === "Text" ? (_(), m(Z, { key: 0 }, [
                    (b.values || []).length > 8 ? (_(), m("div", Aa, [
                      Gt(h("input", {
                        "onUpdate:modelValue": (W) => l.value[b.attribute_id] = W,
                        type: "text",
                        placeholder: "Suchen...",
                        class: "pxc-facets__search-input"
                      }, null, 8, Ea), [
                        [Ur, l.value[b.attribute_id]]
                      ])
                    ])) : N("", !0),
                    (_(!0), m(Z, null, ce(S(b), (W) => (_(), m("label", {
                      key: W.value_id || W.value,
                      class: "pxc-facets__checkbox"
                    }, [
                      h("input", {
                        type: "checkbox",
                        checked: E(b.attribute_id, W.value_id || W.value),
                        onChange: (pe) => B(b.attribute_id, W.value_id || W.value)
                      }, null, 40, Ia),
                      h("span", Oa, A(W.value), 1),
                      h("span", Ha, A(W.count), 1)
                    ]))), 128)),
                    j(b) > 0 && !O(b.attribute_id) ? (_(), m("button", {
                      key: 1,
                      class: "pxc-facets__show-more",
                      onClick: (W) => H(b.attribute_id)
                    }, "Mehr anzeigen (+" + A(j(b)) + ")", 9, Fa)) : O(b.attribute_id) && (b.values || []).length > bs ? (_(), m("button", {
                      key: 2,
                      class: "pxc-facets__show-more",
                      onClick: (W) => H(b.attribute_id)
                    }, "Weniger anzeigen", 8, Da)) : N("", !0)
                  ], 64)) : b.data_type === "Boolean" ? (_(), m("label", Ra, [
                    h("input", {
                      type: "checkbox",
                      checked: g(s).activeFilters[b.attribute_id] === "1",
                      onChange: (W) => z(b.attribute_id)
                    }, null, 40, ja),
                    h("span", null, A(b.label), 1)
                  ])) : b.data_type === "Decimal" || b.data_type === "Integer" ? (_(), m("div", Na, [
                    h("div", Va, [
                      R[3] || (R[3] = h("label", null, "Von", -1)),
                      h("input", {
                        type: "number",
                        placeholder: b.min != null ? String(b.min) : "",
                        value: x(b.attribute_id).min,
                        onChange: (W) => X(b.attribute_id, W.target.value)
                      }, null, 40, Wa)
                    ]),
                    R[5] || (R[5] = h("span", { class: "pxc-facets__range-sep" }, "–", -1)),
                    h("div", Ba, [
                      R[4] || (R[4] = h("label", null, "Bis", -1)),
                      h("input", {
                        type: "number",
                        placeholder: b.max != null ? String(b.max) : "",
                        value: x(b.attribute_id).max,
                        onChange: (W) => ee(b.attribute_id, W.target.value)
                      }, null, 40, Ua)
                    ]),
                    b.unit ? (_(), m("span", Ka, A(b.unit), 1)) : N("", !0)
                  ])) : N("", !0)
                ], 512), [
                  [Ir, !$(b.attribute_id)]
                ])
              ]))), 128))
            ])) : N("", !0)
          ]),
          h("div", Ga, [
            h("button", {
              class: "pxc-btn pxc-btn--outline",
              onClick: R[0] || (R[0] = (b) => {
                D(), a();
              })
            }, "Abbrechen"),
            h("button", {
              class: "pxc-btn pxc-btn--primary",
              onClick: d
            }, "Anwenden")
          ])
        ], 2)
      ]))
    ], 64)) : g(s).facets.length ? (_(), m("div", qa, [
      h("div", Ja, [
        R[6] || (R[6] = h("span", { class: "pxc-facets__title" }, "Filter", -1)),
        g(r).activeFilterCount.value > 0 ? (_(), m("button", {
          key: 0,
          class: "pxc-facets__clear-all",
          onClick: D
        }, "Alle zurücksetzen")) : N("", !0)
      ]),
      (_(!0), m(Z, null, ce(g(s).facets, (b) => (_(), m("div", {
        key: b.attribute_id,
        class: "pxc-facets__group"
      }, [
        h("button", {
          class: "pxc-facets__group-header",
          onClick: (W) => y(b.attribute_id)
        }, [
          h("span", {
            innerHTML: $(b.attribute_id) ? g(U).chevronRight : g(U).chevronDown
          }, null, 8, Za),
          h("span", Ya, A(b.label), 1),
          q(b.attribute_id) > 0 ? (_(), m("span", Qa, A(q(b.attribute_id)), 1)) : N("", !0)
        ], 8, za),
        Gt(h("div", Xa, [
          b.data_type === "ValueList" || b.data_type === "Text" ? (_(), m(Z, { key: 0 }, [
            (b.values || []).length > 8 ? (_(), m("div", eu, [
              Gt(h("input", {
                "onUpdate:modelValue": (W) => l.value[b.attribute_id] = W,
                type: "text",
                placeholder: "Suchen...",
                class: "pxc-facets__search-input"
              }, null, 8, tu), [
                [Ur, l.value[b.attribute_id]]
              ])
            ])) : N("", !0),
            (_(!0), m(Z, null, ce(S(b), (W) => (_(), m("label", {
              key: W.value_id || W.value,
              class: "pxc-facets__checkbox"
            }, [
              h("input", {
                type: "checkbox",
                checked: E(b.attribute_id, W.value_id || W.value),
                onChange: (pe) => B(b.attribute_id, W.value_id || W.value)
              }, null, 40, su),
              h("span", nu, A(W.value), 1),
              h("span", ru, A(W.count), 1)
            ]))), 128)),
            j(b) > 0 && !O(b.attribute_id) ? (_(), m("button", {
              key: 1,
              class: "pxc-facets__show-more",
              onClick: (W) => H(b.attribute_id)
            }, "Mehr anzeigen (+" + A(j(b)) + ")", 9, iu)) : O(b.attribute_id) && (b.values || []).length > bs ? (_(), m("button", {
              key: 2,
              class: "pxc-facets__show-more",
              onClick: (W) => H(b.attribute_id)
            }, "Weniger anzeigen", 8, ou)) : N("", !0)
          ], 64)) : b.data_type === "Boolean" ? (_(), m("label", lu, [
            h("input", {
              type: "checkbox",
              checked: g(s).activeFilters[b.attribute_id] === "1",
              onChange: (W) => z(b.attribute_id)
            }, null, 40, cu),
            h("span", null, A(b.label), 1)
          ])) : b.data_type === "Decimal" || b.data_type === "Integer" ? (_(), m("div", au, [
            h("div", uu, [
              R[7] || (R[7] = h("label", null, "Von", -1)),
              h("input", {
                type: "number",
                placeholder: b.min != null ? String(b.min) : "",
                value: x(b.attribute_id).min,
                onChange: (W) => X(b.attribute_id, W.target.value)
              }, null, 40, fu)
            ]),
            R[9] || (R[9] = h("span", { class: "pxc-facets__range-sep" }, "–", -1)),
            h("div", du, [
              R[8] || (R[8] = h("label", null, "Bis", -1)),
              h("input", {
                type: "number",
                placeholder: b.max != null ? String(b.max) : "",
                value: x(b.attribute_id).max,
                onChange: (W) => ee(b.attribute_id, W.target.value)
              }, null, 40, pu)
            ]),
            b.unit ? (_(), m("span", hu, A(b.unit), 1)) : N("", !0)
          ])) : N("", !0)
        ], 512), [
          [Ir, !$(b.attribute_id)]
        ])
      ]))), 128))
    ])) : N("", !0);
  }
}, gu = { class: "pxc-product-grid" }, mu = {
  key: 0,
  class: "pxc-product-grid__loading"
}, vu = {
  key: 1,
  class: "pxc-product-grid__empty"
}, yu = ["innerHTML"], xu = ["onClick"], bu = { class: "pxc-product-card__image" }, wu = ["src", "alt"], ku = {
  key: 1,
  class: "pxc-product-card__no-image"
}, Cu = ["innerHTML"], $u = ["onClick", "title"], Tu = ["innerHTML"], Su = { class: "pxc-product-card__body" }, Mu = {
  key: 0,
  class: "pxc-product-card__category"
}, Pu = { class: "pxc-product-card__name" }, Lu = {
  key: 1,
  class: "pxc-product-card__sku"
}, Au = {
  key: 2,
  class: "pxc-product-card__attrs"
}, Eu = {
  key: 3,
  class: "pxc-product-card__price"
}, Iu = {
  key: 3,
  class: "pxc-product-grid__overlay"
}, Ou = ["innerHTML"], Hu = {
  __name: "ProductGridWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Se();
    St(() => {
      t.products.length === 0 && !t.loading && s.fetchProducts();
    }), Ae(() => t.locale, () => s.fetchProducts());
    function r(l, c) {
      return l ? new Intl.NumberFormat(t.locale === "de" ? "de-DE" : "en-US", {
        style: "currency",
        currency: c || "EUR"
      }).format(l) : null;
    }
    function i(l) {
      s.openDetail(l.id);
    }
    function o(l, c) {
      l.stopPropagation(), s.toggleWishlist(c);
    }
    return (l, c) => (_(), m("div", gu, [
      g(t).loading && g(t).products.length === 0 ? (_(), m("div", mu, [
        (_(), m(Z, null, ce(8, (u) => h("div", {
          key: u,
          class: "pxc-skeleton pxc-skeleton--card"
        })), 64))
      ])) : g(n).isEmpty.value ? (_(), m("div", vu, [
        h("span", {
          innerHTML: g(U).package,
          style: { width: "48px", height: "48px", opacity: "0.2" }
        }, null, 8, yu),
        c[0] || (c[0] = h("p", null, "Keine Produkte gefunden", -1))
      ])) : (_(), m("div", {
        key: 2,
        class: he(["pxc-product-grid__grid", g(t).viewMode === "list" ? "pxc-product-grid__grid--list" : ""])
      }, [
        (_(!0), m(Z, null, ce(g(t).products, (u) => {
          var a;
          return _(), m("div", {
            key: u.id,
            class: "pxc-product-card",
            onClick: (d) => i(u)
          }, [
            h("div", bu, [
              u.image_url ? (_(), m("img", {
                key: 0,
                src: u.image_url,
                alt: u.name,
                loading: "lazy"
              }, null, 8, wu)) : (_(), m("div", ku, [
                h("span", {
                  innerHTML: g(U).package
                }, null, 8, Cu)
              ])),
              h("button", {
                class: "pxc-product-card__wishlist",
                onClick: (d) => o(d, u.id),
                title: g(n).isInWishlist(u.id) ? "Von Merkliste entfernen" : "Zur Merkliste"
              }, [
                h("span", {
                  innerHTML: g(n).isInWishlist(u.id) ? g(U).heartFilled : g(U).heart,
                  class: he({ "pxc-text-accent": g(n).isInWishlist(u.id) })
                }, null, 10, Tu)
              ], 8, $u)
            ]),
            h("div", Su, [
              u.category_path ? (_(), m("p", Mu, A(u.category_path), 1)) : N("", !0),
              h("h3", Pu, A(u.primary_attribute_value && u.primary_attribute_value.trim() || u.name || u.sku || "–"), 1),
              u.sku ? (_(), m("p", Lu, A(u.sku), 1)) : N("", !0),
              (a = u.card_attributes) != null && a.length ? (_(), m("div", Au, [
                (_(!0), m(Z, null, ce(u.card_attributes.slice(0, 3), (d, y) => (_(), m("span", { key: y }, A(d.value), 1))), 128))
              ])) : N("", !0),
              u.price ? (_(), m("div", Eu, A(r(u.price, u.currency)), 1)) : N("", !0)
            ])
          ], 8, xu);
        }), 128))
      ], 2)),
      g(t).loading && g(t).products.length > 0 ? (_(), m("div", Iu, [
        h("span", {
          innerHTML: g(U).loader,
          style: { width: "32px", height: "32px" }
        }, null, 8, Ou)
      ])) : N("", !0)
    ]));
  }
}, Fu = {
  key: 0,
  class: "pxc-pagination"
}, Du = { class: "pxc-pagination__info" }, Ru = { class: "pxc-pagination__buttons" }, ju = ["disabled"], Nu = ["innerHTML"], Vu = {
  key: 0,
  disabled: "",
  class: "pxc-pagination__dots"
}, Wu = ["onClick"], Bu = ["disabled"], Uu = ["innerHTML"], Ku = {
  __name: "PaginationWidget",
  setup(e) {
    const { state: t, actions: s } = Se(), n = ge(() => {
      const { current_page: o, last_page: l } = t.meta;
      if (l <= 1) return [];
      const c = [], u = 5;
      let a = Math.max(1, o - Math.floor(u / 2)), d = Math.min(l, a + u - 1);
      a = Math.max(1, d - u + 1), a > 1 && (c.push(1), a > 2 && c.push("..."));
      for (let y = a; y <= d; y++) c.push(y);
      return d < l && (d < l - 1 && c.push("..."), c.push(l)), c;
    }), r = ge(() => {
      const { current_page: o, per_page: l, total: c } = t.meta;
      return {
        from: (o - 1) * l + 1,
        to: Math.min(o * l, c)
      };
    });
    function i(o) {
      typeof o == "number" && (s.setPage(o), s.fetchProducts(), window.scrollTo({ top: 0, behavior: "smooth" }));
    }
    return (o, l) => g(t).meta.last_page > 1 ? (_(), m("div", Fu, [
      h("p", Du, A(r.value.from) + "–" + A(r.value.to) + " von " + A(g(t).meta.total), 1),
      h("div", Ru, [
        h("button", {
          disabled: g(t).meta.current_page <= 1,
          onClick: l[0] || (l[0] = (c) => i(g(t).meta.current_page - 1))
        }, [
          h("span", {
            innerHTML: g(U).chevronLeft
          }, null, 8, Nu)
        ], 8, ju),
        (_(!0), m(Z, null, ce(n.value, (c, u) => (_(), m(Z, { key: u }, [
          c === "..." ? (_(), m("button", Vu, "...")) : (_(), m("button", {
            key: 1,
            class: he({ "pxc-pagination__active": c === g(t).meta.current_page }),
            onClick: (a) => i(c)
          }, A(c), 11, Wu))
        ], 64))), 128)),
        h("button", {
          disabled: g(t).meta.current_page >= g(t).meta.last_page,
          onClick: l[1] || (l[1] = (c) => i(g(t).meta.current_page + 1))
        }, [
          h("span", {
            innerHTML: g(U).chevronRight
          }, null, 8, Uu)
        ], 8, Bu)
      ])
    ])) : N("", !0);
  }
}, Gu = { class: "pxc-toolbar" }, qu = { class: "pxc-toolbar__count" }, Ju = { class: "pxc-toolbar__actions" }, zu = { class: "pxc-toolbar__sort" }, Zu = ["value"], Yu = ["title"], Qu = ["innerHTML"], Xu = { class: "pxc-toolbar__view" }, ef = ["innerHTML"], tf = ["innerHTML"], sf = {
  __name: "ToolbarWidget",
  setup(e) {
    const { state: t, actions: s } = Se();
    function n() {
      const o = t.sort.order === "asc" ? "desc" : "asc";
      s.setSort(t.sort.field, o), s.fetchProducts();
    }
    function r(o) {
      s.setSort(o.target.value, t.sort.order), s.fetchProducts();
    }
    function i(o) {
      s.setViewMode(o);
    }
    return (o, l) => (_(), m("div", Gu, [
      h("span", qu, [
        me(A(g(t).meta.total) + " Produkte ", 1),
        g(t).selectedCategoryName ? (_(), m(Z, { key: 0 }, [
          l[2] || (l[2] = me(" in ", -1)),
          h("strong", null, A(g(t).selectedCategoryName), 1)
        ], 64)) : N("", !0)
      ]),
      h("div", Ju, [
        h("div", zu, [
          h("select", {
            value: g(t).sort.field,
            onChange: r
          }, [...l[3] || (l[3] = [
            h("option", { value: "name" }, "Name", -1),
            h("option", { value: "sku" }, "Artikelnummer", -1),
            h("option", { value: "created_at" }, "Neu", -1),
            h("option", { value: "updated_at" }, "Aktualisiert", -1)
          ])], 40, Zu),
          h("button", {
            onClick: n,
            title: g(t).sort.order === "asc" ? "Aufsteigend" : "Absteigend"
          }, [
            h("span", {
              innerHTML: g(t).sort.order === "asc" ? g(U).sortAsc : g(U).sortDesc
            }, null, 8, Qu)
          ], 8, Yu)
        ]),
        h("div", Xu, [
          h("button", {
            class: he({ "pxc-toolbar__view--active": g(t).viewMode === "grid" }),
            onClick: l[0] || (l[0] = (c) => i("grid")),
            innerHTML: g(U).grid
          }, null, 10, ef),
          h("button", {
            class: he({ "pxc-toolbar__view--active": g(t).viewMode === "list" }),
            onClick: l[1] || (l[1] = (c) => i("list")),
            innerHTML: g(U).list
          }, null, 10, tf)
        ])
      ])
    ]));
  }
}, nf = { class: "pxc-wishlist" }, rf = ["innerHTML"], of = {
  key: 0,
  class: "pxc-wishlist__badge"
}, lf = { class: "pxc-wishlist__drawer-header" }, cf = ["innerHTML"], af = {
  key: 0,
  class: "pxc-wishlist__badge"
}, uf = ["innerHTML"], ff = {
  key: 0,
  class: "pxc-wishlist__empty"
}, df = ["innerHTML"], pf = {
  key: 1,
  class: "pxc-wishlist__items"
}, hf = { class: "pxc-wishlist__item-image" }, _f = ["src", "alt"], gf = ["innerHTML"], mf = { class: "pxc-wishlist__item-info" }, vf = { class: "pxc-wishlist__item-name" }, yf = { class: "pxc-wishlist__item-sku" }, xf = {
  key: 0,
  class: "pxc-wishlist__item-price"
}, bf = ["onClick"], wf = ["innerHTML"], kf = {
  key: 0,
  class: "pxc-text-muted",
  style: { "text-align": "center", padding: "8px" }
}, Cf = {
  key: 2,
  class: "pxc-wishlist__footer"
}, $f = ["disabled"], Tf = ["innerHTML"], Sf = ["disabled"], Mf = ["innerHTML"], Pf = ["innerHTML"], Lf = ["innerHTML"], Af = ["innerHTML"], Ef = {
  __name: "WishlistWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Se(), r = /* @__PURE__ */ He(!1), i = /* @__PURE__ */ He(null), o = /* @__PURE__ */ He(!1);
    function l() {
      r.value = !0;
    }
    St(() => window.addEventListener("pxc:open-wishlist", l)), zs(() => window.removeEventListener("pxc:open-wishlist", l));
    const c = ge(() => t.products.filter((B) => n.isInWishlist(B.id))), u = ge(() => {
      const B = new Set(t.products.map((E) => E.id));
      return t.wishlistIds.filter((E) => !B.has(E)).length;
    }), a = ge(
      () => t.settings.catalog_compare_enabled && n.wishlistCount.value >= 2 && n.wishlistCount.value <= (t.settings.catalog_compare_max_products || 3)
    );
    function d() {
      r.value = !r.value;
    }
    function y(B) {
      return B ? new Intl.NumberFormat(t.locale === "de" ? "de-DE" : "en-US", {
        style: "currency",
        currency: "EUR"
      }).format(B) : null;
    }
    async function $() {
      if (!i.value) {
        i.value = "pdf";
        try {
          await s.downloadWishlistPdf();
        } catch (B) {
          console.error("PDF export failed:", B);
        } finally {
          i.value = null;
        }
      }
    }
    async function H() {
      if (!i.value) {
        i.value = "excel";
        try {
          await s.downloadWishlistExcel();
        } catch (B) {
          console.error("Excel export failed:", B);
        } finally {
          i.value = null;
        }
      }
    }
    function O() {
      s.openCompare([...t.wishlistIds]);
    }
    async function Y() {
      const E = `${window.location.href.split("?")[0]}?wishlist=${t.wishlistIds.join(",")}`;
      try {
        await navigator.clipboard.writeText(E), o.value = !0, setTimeout(() => {
          o.value = !1;
        }, 2e3);
      } catch {
      }
    }
    return (B, E) => (_(), m("div", nf, [
      h("button", {
        class: "pxc-wishlist__toggle",
        onClick: d
      }, [
        h("span", {
          innerHTML: g(U).heart
        }, null, 8, rf),
        g(n).wishlistCount.value > 0 ? (_(), m("span", of, A(g(n).wishlistCount.value), 1)) : N("", !0)
      ]),
      r.value ? (_(), m("div", {
        key: 0,
        class: "pxc-wishlist__overlay",
        onClick: d
      })) : N("", !0),
      h("div", {
        class: he(["pxc-wishlist__drawer", { "pxc-wishlist__drawer--open": r.value }])
      }, [
        h("div", lf, [
          h("span", {
            innerHTML: g(U).heart,
            class: "pxc-text-accent"
          }, null, 8, cf),
          E[1] || (E[1] = h("span", null, "Merkliste", -1)),
          g(n).wishlistCount.value ? (_(), m("span", af, A(g(n).wishlistCount.value), 1)) : N("", !0),
          h("button", {
            class: "pxc-wishlist__close",
            onClick: d,
            innerHTML: g(U).x
          }, null, 8, uf)
        ]),
        g(n).wishlistCount.value === 0 ? (_(), m("div", ff, [
          h("span", {
            innerHTML: g(U).heart,
            style: { width: "40px", height: "40px", opacity: "0.15" }
          }, null, 8, df),
          E[2] || (E[2] = h("p", null, "Merkliste ist leer", -1)),
          E[3] || (E[3] = h("p", { class: "pxc-text-muted" }, "Klicken Sie auf das Herz-Symbol bei einem Produkt", -1))
        ])) : (_(), m("div", pf, [
          (_(!0), m(Z, null, ce(c.value, (z) => (_(), m("div", {
            key: z.id,
            class: "pxc-wishlist__item"
          }, [
            h("div", hf, [
              z.image_url ? (_(), m("img", {
                key: 0,
                src: z.image_url,
                alt: z.name
              }, null, 8, _f)) : (_(), m("span", {
                key: 1,
                innerHTML: g(U).package
              }, null, 8, gf))
            ]),
            h("div", mf, [
              h("p", vf, A(z.name), 1),
              h("p", yf, A(z.sku), 1),
              z.price ? (_(), m("p", xf, A(y(z.price)), 1)) : N("", !0)
            ]),
            h("button", {
              class: "pxc-wishlist__item-remove",
              onClick: (x) => g(s).toggleWishlist(z.id)
            }, [
              h("span", {
                innerHTML: g(U).trash
              }, null, 8, wf)
            ], 8, bf)
          ]))), 128)),
          u.value > 0 ? (_(), m("div", kf, " + " + A(u.value) + " weitere Produkte ", 1)) : N("", !0)
        ])),
        g(n).wishlistCount.value > 0 ? (_(), m("div", Cf, [
          g(t).settings.catalog_pdf_enabled ? (_(), m("button", {
            key: 0,
            class: "pxc-btn pxc-btn--primary",
            onClick: $,
            disabled: !!i.value
          }, [
            h("span", {
              innerHTML: g(U).fileDown
            }, null, 8, Tf),
            me(" " + A(i.value === "pdf" ? "Exportiere..." : "Als PDF"), 1)
          ], 8, $f)) : N("", !0),
          g(t).settings.catalog_excel_export_enabled ? (_(), m("button", {
            key: 1,
            class: "pxc-btn pxc-btn--outline",
            onClick: H,
            disabled: !!i.value
          }, [
            h("span", {
              innerHTML: g(U).sheet
            }, null, 8, Mf),
            me(" " + A(i.value === "excel" ? "Exportiere..." : "Excel-Export"), 1)
          ], 8, Sf)) : N("", !0),
          a.value ? (_(), m("button", {
            key: 2,
            class: "pxc-btn pxc-btn--outline",
            onClick: O
          }, [
            h("span", {
              innerHTML: g(U).compare
            }, null, 8, Pf),
            me(" Vergleichen (" + A(g(n).wishlistCount.value) + ") ", 1)
          ])) : N("", !0),
          g(t).settings.catalog_share_wishlist_enabled ? (_(), m("button", {
            key: 3,
            class: "pxc-btn pxc-btn--ghost",
            onClick: Y
          }, [
            h("span", {
              innerHTML: o.value ? g(U).check : g(U).share
            }, null, 8, Lf),
            me(" " + A(o.value ? "Link kopiert!" : "Teilen"), 1)
          ])) : N("", !0),
          h("button", {
            class: "pxc-btn pxc-btn--danger",
            onClick: E[0] || (E[0] = (z) => g(s).clearWishlist())
          }, [
            h("span", {
              innerHTML: g(U).trash
            }, null, 8, Af),
            E[4] || (E[4] = me(" Leeren ", -1))
          ])
        ])) : N("", !0)
      ], 2)
    ]));
  }
}, If = ["innerHTML"], Of = {
  key: 0,
  class: "pxc-wishlist-btn__badge"
}, Hf = {
  __name: "WishlistButtonWidget",
  setup(e) {
    const { state: t, getters: s } = Se();
    function n() {
      window.dispatchEvent(new CustomEvent("pxc:open-wishlist"));
    }
    return (r, i) => (_(), m("button", {
      class: "pxc-wishlist-btn",
      onClick: n
    }, [
      h("span", {
        innerHTML: g(U).heart
      }, null, 8, If),
      i[0] || (i[0] = h("span", null, "Merkliste", -1)),
      g(s).wishlistCount.value > 0 ? (_(), m("span", Of, A(g(s).wishlistCount.value), 1)) : N("", !0)
    ]));
  }
}, Ff = { class: "pxc-detail-modal" }, Df = ["innerHTML"], Rf = {
  key: 0,
  class: "pxc-detail-modal__loading"
}, jf = ["innerHTML"], Nf = {
  key: 1,
  class: "pxc-detail"
}, Vf = { class: "pxc-detail__layout" }, Wf = { class: "pxc-detail__gallery" }, Bf = { class: "pxc-detail__main-image" }, Uf = ["src", "alt"], Kf = {
  key: 1,
  class: "pxc-detail__no-image"
}, Gf = ["innerHTML"], qf = ["innerHTML"], Jf = ["innerHTML"], zf = {
  key: 0,
  class: "pxc-detail__thumbs"
}, Zf = ["onClick"], Yf = ["src", "alt"], Qf = { class: "pxc-detail__info" }, Xf = {
  key: 0,
  class: "pxc-detail__breadcrumb"
}, ed = { class: "pxc-detail__title" }, td = { class: "pxc-detail__meta" }, sd = { key: 0 }, nd = { key: 1 }, rd = {
  key: 1,
  class: "pxc-detail__description"
}, id = {
  key: 2,
  class: "pxc-detail__prices"
}, od = { class: "pxc-detail__price-label" }, ld = { class: "pxc-detail__price-value" }, cd = { class: "pxc-detail__actions" }, ad = ["innerHTML"], ud = ["innerHTML"], fd = { class: "pxc-detail__tabs" }, dd = ["onClick"], pd = {
  key: 3,
  class: "pxc-detail__tab-content"
}, hd = {
  key: 0,
  class: "pxc-detail__group-header"
}, _d = { class: "pxc-detail__table" }, gd = { class: "pxc-detail__table-label" }, md = { class: "pxc-detail__table-value" }, vd = ["href"], yd = ["href"], xd = {
  key: 3,
  class: "pxc-text-muted"
}, bd = {
  key: 1,
  class: "pxc-detail__empty"
}, wd = {
  key: 4,
  class: "pxc-detail__tab-content"
}, kd = {
  key: 0,
  class: "pxc-detail__documents"
}, Cd = ["href"], $d = ["innerHTML"], Td = { class: "pxc-detail__doc-info" }, Sd = { class: "pxc-detail__doc-name" }, Md = { class: "pxc-detail__doc-type" }, Pd = {
  key: 1,
  class: "pxc-detail__empty"
}, Ld = {
  key: 5,
  class: "pxc-detail__tab-content"
}, Ad = { class: "pxc-detail__relation-type" }, Ed = { class: "pxc-detail__relation-items" }, Id = ["onClick"], Od = { class: "pxc-detail__relation-img" }, Hd = ["src", "alt"], Fd = ["innerHTML"], Dd = { class: "pxc-detail__relation-info" }, Rd = { class: "pxc-detail__relation-name" }, jd = {
  key: 0,
  class: "pxc-detail__relation-sku"
}, Nd = {
  key: 2,
  class: "pxc-detail-modal__error"
}, Vd = {
  __name: "ProductDetailWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Se(), r = /* @__PURE__ */ He(0), i = /* @__PURE__ */ He("attributes");
    Ae(() => t.detailOpen, (x) => {
      document.body.style.overflow = x ? "hidden" : "", x && (i.value = "attributes", r.value = 0, t.attributeGroups.length === 0 && s.fetchAttributeGroups());
    });
    const o = ge(() => {
      var x;
      return (x = t.currentProduct) != null && x.media ? t.currentProduct.media.filter((V) => V.media_type === "image") : [];
    }), l = ge(() => {
      var x;
      return (x = t.currentProduct) != null && x.media ? t.currentProduct.media.filter((V) => V.media_type !== "image") : [];
    }), c = ge(() => o.value[r.value]);
    Ae(() => t.currentProduct, () => {
      r.value = 0;
    });
    const u = ge(() => {
      var X;
      const x = (X = t.currentProduct) == null ? void 0 : X.attributes;
      if (!(x != null && x.length)) return [];
      const V = /* @__PURE__ */ new Set();
      return x.forEach((ee) => {
        ee.parent_attribute_id && V.add(ee.attribute_id);
      }), x.filter((ee) => !ee.parent_attribute_id || !V.has(ee.attribute_id));
    }), a = ge(() => {
      const x = u.value;
      if (!x.length) return [];
      const V = t.attributeGroups || [], X = {};
      for (const M of V)
        X[M.id] = M.name;
      if (!(new Set(x.map((M) => M.group_id).filter(Boolean)).size > 1))
        return [{ name: null, attrs: x }];
      const S = {};
      V.forEach((M, R) => {
        S[M.id] = R;
      });
      const j = {}, q = [];
      for (const M of x)
        M.group_id && X[M.group_id] ? (j[M.group_id] || (j[M.group_id] = {
          name: X[M.group_id],
          sortOrder: S[M.group_id] ?? 999,
          attrs: []
        }), j[M.group_id].attrs.push(M)) : q.push(M);
      const D = Object.values(j).sort((M, R) => M.sortOrder - R.sortOrder);
      return q.length && D.push({ name: t.locale === "de" ? "Sonstige" : "Other", attrs: q }), D;
    }), d = ge(() => {
      var X;
      const x = (X = t.currentProduct) == null ? void 0 : X.relations;
      if (!(x != null && x.length)) return [];
      const V = {};
      for (const ee of x) {
        const se = ee.relation_type_id || "default";
        V[se] || (V[se] = {
          type_id: se,
          type_name: ee.relation_type || (t.locale === "de" ? "Verwandte Produkte" : "Related Products"),
          products: []
        }), V[se].products.push({
          id: ee.target_product_id,
          name: ee.name,
          sku: ee.sku,
          image_url: ee.image_url ? fo(ee.image_url) : null
        });
      }
      return Object.values(V);
    }), y = ge(() => d.value.length > 0), $ = ge(() => l.value.length > 0), H = ge(() => {
      const x = [{ key: "attributes", label: t.locale === "de" ? "Eigenschaften" : "Attributes" }];
      return $.value && x.push({ key: "media", label: t.locale === "de" ? "Dokumente" : "Documents" }), y.value && x.push({ key: "relations", label: t.locale === "de" ? "Beziehungen" : "Relations" }), x;
    });
    function O() {
      r.value = r.value > 0 ? r.value - 1 : o.value.length - 1;
    }
    function Y() {
      r.value = r.value < o.value.length - 1 ? r.value + 1 : 0;
    }
    function B(x, V) {
      return x ? new Intl.NumberFormat(t.locale === "de" ? "de-DE" : "en-US", {
        style: "currency",
        currency: V || "EUR"
      }).format(x) : null;
    }
    async function E() {
      t.currentProduct && await s.downloadProductPdf(t.currentProduct.id);
    }
    function z(x) {
      return x != null && x.includes("pdf") ? U.fileDown : x != null && x.includes("sheet") || x != null && x.includes("excel") ? U.sheet : U.fileDown;
    }
    return (x, V) => (_(), ps(Gs, { to: "body" }, [
      ye(Xs, { name: "pxc-fade" }, {
        default: ds(() => {
          var X, ee, se;
          return [
            g(t).detailOpen ? (_(), m("div", {
              key: 0,
              class: "pxc-detail-overlay",
              onClick: V[2] || (V[2] = ao((S) => g(s).closeDetail(), ["self"]))
            }, [
              h("div", Ff, [
                h("button", {
                  class: "pxc-detail-modal__close",
                  onClick: V[0] || (V[0] = (S) => g(s).closeDetail()),
                  innerHTML: g(U).x
                }, null, 8, Df),
                g(t).productLoading ? (_(), m("div", Rf, [
                  h("span", {
                    innerHTML: g(U).loader,
                    style: { width: "32px", height: "32px" }
                  }, null, 8, jf),
                  h("p", null, A(g(t).locale === "de" ? "Lade Produktdetails…" : "Loading product…"), 1)
                ])) : g(t).currentProduct ? (_(), m("div", Nf, [
                  h("div", Vf, [
                    h("div", Wf, [
                      h("div", Bf, [
                        c.value ? (_(), m("img", {
                          key: 0,
                          src: c.value.url,
                          alt: c.value.alt || ""
                        }, null, 8, Uf)) : (_(), m("div", Kf, [
                          h("span", {
                            innerHTML: g(U).package,
                            style: { width: "64px", height: "64px", opacity: "0.1" }
                          }, null, 8, Gf)
                        ])),
                        o.value.length > 1 ? (_(), m(Z, { key: 2 }, [
                          h("button", {
                            class: "pxc-detail__nav pxc-detail__nav--prev",
                            onClick: O,
                            innerHTML: g(U).chevronLeft
                          }, null, 8, qf),
                          h("button", {
                            class: "pxc-detail__nav pxc-detail__nav--next",
                            onClick: Y,
                            innerHTML: g(U).chevronRight
                          }, null, 8, Jf)
                        ], 64)) : N("", !0)
                      ]),
                      o.value.length > 1 ? (_(), m("div", zf, [
                        (_(!0), m(Z, null, ce(o.value, (S, j) => (_(), m("button", {
                          key: S.url,
                          class: he(["pxc-detail__thumb", { "pxc-detail__thumb--active": j === r.value }]),
                          onClick: (q) => r.value = j
                        }, [
                          h("img", {
                            src: S.url,
                            alt: S.alt || ""
                          }, null, 8, Yf)
                        ], 10, Zf))), 128))
                      ])) : N("", !0)
                    ]),
                    h("div", Qf, [
                      (X = g(t).currentProduct.category_breadcrumb) != null && X.length ? (_(), m("p", Xf, [
                        (_(!0), m(Z, null, ce(g(t).currentProduct.category_breadcrumb, (S, j) => (_(), m("span", { key: j }, [
                          me(A(S.name), 1),
                          j < g(t).currentProduct.category_breadcrumb.length - 1 ? (_(), m(Z, { key: 0 }, [
                            me(" / ")
                          ], 64)) : N("", !0)
                        ]))), 128))
                      ])) : N("", !0),
                      h("h2", ed, A(g(t).currentProduct.name), 1),
                      h("div", td, [
                        g(t).currentProduct.sku ? (_(), m("span", sd, "SKU: " + A(g(t).currentProduct.sku), 1)) : N("", !0),
                        g(t).currentProduct.ean ? (_(), m("span", nd, "EAN: " + A(g(t).currentProduct.ean), 1)) : N("", !0)
                      ]),
                      (ee = g(t).currentProduct.description_attributes) != null && ee.length ? (_(), m("div", rd, [
                        (_(!0), m(Z, null, ce(g(t).currentProduct.description_attributes, (S) => (_(), m("div", {
                          key: S.attribute_id,
                          class: he("pxc-detail__desc-" + (S.typography || "base"))
                        }, A(S.value), 3))), 128))
                      ])) : N("", !0),
                      (se = g(t).currentProduct.prices) != null && se.length ? (_(), m("div", id, [
                        (_(!0), m(Z, null, ce(g(t).currentProduct.prices, (S, j) => (_(), m("div", {
                          key: j,
                          class: "pxc-detail__price"
                        }, [
                          h("span", od, A(S.type_name || "Preis"), 1),
                          h("span", ld, A(B(S.amount, S.currency)), 1)
                        ]))), 128))
                      ])) : N("", !0),
                      h("div", cd, [
                        h("button", {
                          class: he(["pxc-btn", g(n).isInWishlist(g(t).currentProduct.id) ? "pxc-btn--accent" : "pxc-btn--outline"]),
                          onClick: V[1] || (V[1] = (S) => g(s).toggleWishlist(g(t).currentProduct.id))
                        }, [
                          h("span", {
                            innerHTML: g(n).isInWishlist(g(t).currentProduct.id) ? g(U).heartFilled : g(U).heart
                          }, null, 8, ad),
                          me(" " + A(g(n).isInWishlist(g(t).currentProduct.id) ? g(t).locale === "de" ? "Auf Merkliste" : "On Wishlist" : g(t).locale === "de" ? "Zur Merkliste" : "Add to Wishlist"), 1)
                        ], 2),
                        g(t).settings.catalog_pdf_enabled ? (_(), m("button", {
                          key: 0,
                          class: "pxc-btn pxc-btn--outline",
                          onClick: E
                        }, [
                          h("span", {
                            innerHTML: g(U).fileDown
                          }, null, 8, ud),
                          V[3] || (V[3] = me(" PDF ", -1))
                        ])) : N("", !0)
                      ]),
                      h("div", fd, [
                        (_(!0), m(Z, null, ce(H.value, (S) => (_(), m("button", {
                          key: S.key,
                          class: he(["pxc-detail__tab", { "pxc-detail__tab--active": i.value === S.key }]),
                          onClick: (j) => i.value = S.key
                        }, A(S.label), 11, dd))), 128))
                      ]),
                      i.value === "attributes" ? (_(), m("div", pd, [
                        a.value.length ? (_(!0), m(Z, { key: 0 }, ce(a.value, (S, j) => (_(), m("div", {
                          key: j,
                          class: he(j > 0 ? "pxc-mt-4" : "")
                        }, [
                          S.name ? (_(), m("h4", hd, A(S.name), 1)) : N("", !0),
                          h("table", _d, [
                            h("tbody", null, [
                              (_(!0), m(Z, null, ce(S.attrs, (q) => (_(), m("tr", {
                                key: q.attribute_id
                              }, [
                                h("td", gd, A(q.label), 1),
                                h("td", md, [
                                  q.link_data ? (_(), m("a", {
                                    key: 0,
                                    href: q.link_data.url,
                                    target: "_blank",
                                    rel: "noopener"
                                  }, A(q.link_data.title || q.link_data.url), 9, vd)) : q.data_type === "Hyperlink" ? (_(), m("a", {
                                    key: 1,
                                    href: q.value,
                                    target: "_blank",
                                    rel: "noopener"
                                  }, A(q.value), 9, yd)) : (_(), m(Z, { key: 2 }, [
                                    me(A(q.value || "—"), 1)
                                  ], 64)),
                                  q.unit ? (_(), m("span", xd, A(q.unit), 1)) : N("", !0)
                                ])
                              ]))), 128))
                            ])
                          ])
                        ], 2))), 128)) : (_(), m("p", bd, A(g(t).locale === "de" ? "Keine Eigenschaften vorhanden." : "No attributes available."), 1))
                      ])) : N("", !0),
                      i.value === "media" ? (_(), m("div", wd, [
                        l.value.length ? (_(), m("div", kd, [
                          (_(!0), m(Z, null, ce(l.value, (S) => (_(), m("a", {
                            key: S.file_name,
                            href: S.url,
                            target: "_blank",
                            rel: "noopener",
                            class: "pxc-detail__doc-item"
                          }, [
                            h("span", {
                              class: "pxc-detail__doc-icon",
                              innerHTML: z(S.mime_type)
                            }, null, 8, $d),
                            h("div", Td, [
                              h("span", Sd, A(S.description || S.file_name), 1),
                              h("span", Md, A(S.mime_type), 1)
                            ])
                          ], 8, Cd))), 128))
                        ])) : (_(), m("p", Pd, A(g(t).locale === "de" ? "Keine Dokumente vorhanden." : "No documents available."), 1))
                      ])) : N("", !0),
                      i.value === "relations" ? (_(), m("div", Ld, [
                        (_(!0), m(Z, null, ce(d.value, (S) => (_(), m("div", {
                          key: S.type_id,
                          class: "pxc-detail__relation-group"
                        }, [
                          h("h4", Ad, A(S.type_name), 1),
                          h("div", Ed, [
                            (_(!0), m(Z, null, ce(S.products, (j) => (_(), m("div", {
                              key: j.id,
                              class: "pxc-detail__relation-card",
                              onClick: (q) => g(s).openDetail(j.id)
                            }, [
                              h("div", Od, [
                                j.image_url ? (_(), m("img", {
                                  key: 0,
                                  src: j.image_url,
                                  alt: j.name
                                }, null, 8, Hd)) : (_(), m("span", {
                                  key: 1,
                                  innerHTML: g(U).package,
                                  class: "pxc-detail__relation-placeholder"
                                }, null, 8, Fd))
                              ]),
                              h("div", Dd, [
                                h("p", Rd, A(j.name), 1),
                                j.sku ? (_(), m("span", jd, A(j.sku), 1)) : N("", !0)
                              ])
                            ], 8, Id))), 128))
                          ])
                        ]))), 128))
                      ])) : N("", !0)
                    ])
                  ])
                ])) : g(t).error ? (_(), m("div", Nd, [
                  h("p", null, A(g(t).error), 1)
                ])) : N("", !0)
              ])
            ])) : N("", !0)
          ];
        }),
        _: 1
      })
    ]));
  }
}, Wd = { class: "pxc-compare-modal" }, Bd = { class: "pxc-compare-modal__header" }, Ud = ["innerHTML"], Kd = {
  key: 0,
  class: "pxc-text-muted"
}, Gd = { class: "pxc-compare-modal__filter" }, qd = ["innerHTML"], Jd = { class: "pxc-compare-modal__body" }, zd = {
  key: 0,
  class: "pxc-compare-modal__loading"
}, Zd = {
  key: 1,
  class: "pxc-compare-table"
}, Yd = { class: "pxc-text-muted" }, Qd = { key: 0 }, Xd = ["colspan"], ep = {
  __name: "CompareWidget",
  setup(e) {
    const { state: t, actions: s } = Se(), n = /* @__PURE__ */ He(!1);
    Ae(() => t.compareOpen, (i) => {
      document.body.style.overflow = i ? "hidden" : "";
    });
    const r = ge(() => {
      var i;
      return (i = t.compareData) != null && i.rows ? n.value ? t.compareData.rows.filter((o) => o.is_different) : t.compareData.rows : [];
    });
    return (i, o) => (_(), ps(Gs, { to: "body" }, [
      ye(Xs, { name: "pxc-fade" }, {
        default: ds(() => {
          var l, c;
          return [
            g(t).compareOpen ? (_(), m("div", {
              key: 0,
              class: "pxc-compare-overlay",
              onClick: o[2] || (o[2] = ao((u) => g(s).closeCompare(), ["self"]))
            }, [
              h("div", Wd, [
                h("div", Bd, [
                  h("span", {
                    innerHTML: g(U).compare
                  }, null, 8, Ud),
                  o[4] || (o[4] = h("span", null, "Produktvergleich", -1)),
                  g(t).compareData ? (_(), m("span", Kd, A(g(t).compareData.total_differences) + " Unterschiede von " + A(g(t).compareData.total_attributes) + " Feldern ", 1)) : N("", !0),
                  o[5] || (o[5] = h("div", { style: { flex: "1" } }, null, -1)),
                  h("label", Gd, [
                    Gt(h("input", {
                      type: "checkbox",
                      "onUpdate:modelValue": o[0] || (o[0] = (u) => n.value = u)
                    }, null, 512), [
                      [Hc, n.value]
                    ]),
                    o[3] || (o[3] = me(" Nur Unterschiede ", -1))
                  ]),
                  h("button", {
                    class: "pxc-btn pxc-btn--ghost",
                    onClick: o[1] || (o[1] = (u) => g(s).closeCompare()),
                    innerHTML: g(U).x
                  }, null, 8, qd)
                ]),
                h("div", Jd, [
                  g(t).compareLoading ? (_(), m("div", zd, [
                    (_(), m(Z, null, ce(8, (u) => h("div", {
                      key: u,
                      class: "pxc-skeleton",
                      style: { height: "32px", "margin-bottom": "4px" }
                    })), 64))
                  ])) : g(t).compareData ? (_(), m("table", Zd, [
                    h("thead", null, [
                      h("tr", null, [
                        o[6] || (o[6] = h("th", null, "Attribut", -1)),
                        (_(!0), m(Z, null, ce(g(t).compareData.products, (u) => (_(), m("th", {
                          key: u.id
                        }, [
                          me(A(u.sku) + " ", 1),
                          h("span", Yd, A(u.name), 1)
                        ]))), 128))
                      ])
                    ]),
                    h("tbody", null, [
                      (_(!0), m(Z, null, ce(r.value, (u, a) => (_(), m("tr", {
                        key: a,
                        class: he({ "pxc-compare-table__diff": u.is_different })
                      }, [
                        h("td", null, A(u.attribute_name), 1),
                        (_(!0), m(Z, null, ce(u.values, (d, y) => (_(), m("td", { key: y }, A(d ?? "—"), 1))), 128))
                      ], 2))), 128)),
                      r.value.length === 0 ? (_(), m("tr", Qd, [
                        h("td", {
                          colspan: 1 + (((c = (l = g(t).compareData) == null ? void 0 : l.products) == null ? void 0 : c.length) || 0),
                          style: { "text-align": "center", padding: "32px" }
                        }, A(n.value ? "Keine Unterschiede" : "Keine Attribute"), 9, Xd)
                      ])) : N("", !0)
                    ])
                  ])) : N("", !0)
                ])
              ])
            ])) : N("", !0)
          ];
        }),
        _: 1
      })
    ]));
  }
}, tp = { class: "pxc-locale" }, sp = ["innerHTML"], np = {
  __name: "LocaleWidget",
  setup(e) {
    const { state: t, actions: s } = Se();
    function n(r) {
      s.setLocale(r), s.fetchProducts(), s.fetchCategories();
    }
    return (r, i) => (_(), m("div", tp, [
      h("span", {
        innerHTML: g(U).globe
      }, null, 8, sp),
      h("button", {
        class: he(["pxc-locale__btn", { "pxc-locale__btn--active": g(t).locale === "de" }]),
        onClick: i[0] || (i[0] = (o) => n("de"))
      }, "DE", 2),
      h("button", {
        class: he(["pxc-locale__btn", { "pxc-locale__btn--active": g(t).locale === "en" }]),
        onClick: i[1] || (i[1] = (o) => n("en"))
      }, "EN", 2)
    ]));
  }
}, rp = {
  key: 0,
  class: "pxc-active-filters"
}, ip = ["onClick", "innerHTML"], op = {
  __name: "ActiveFiltersWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Se(), r = ge(() => {
      var c;
      const l = [];
      t.selectedCategoryName && l.push({ type: "category", label: t.selectedCategoryName }), t.search && l.push({ type: "search", label: `"${t.search}"` });
      for (const [u, a] of Object.entries(t.activeFilters)) {
        const d = t.facets.find((H) => String(H.attribute_id) === String(u)), y = d ? d.label : `Filter ${u}`;
        let $ = a;
        (c = d == null ? void 0 : d.values) != null && c.length && ($ = a.split(",").filter(Boolean).map((Y) => {
          const B = d.values.find((E) => String(E.value_id) === Y);
          return B ? B.value : Y;
        }).join(", ")), l.push({ type: "filter", attrId: u, label: `${y}: ${$}` });
      }
      return l;
    });
    function i(l) {
      l.type === "category" ? s.clearCategory() : l.type === "search" ? s.setSearch("") : l.type === "filter" && s.clearFilter(l.attrId), s.fetchProducts();
    }
    function o() {
      s.setSearch(""), s.clearCategory(), s.clearAllFilters(), s.fetchProducts();
    }
    return (l, c) => r.value.length > 0 ? (_(), m("div", rp, [
      (_(!0), m(Z, null, ce(r.value, (u, a) => (_(), m("span", {
        key: a,
        class: "pxc-active-filters__chip"
      }, [
        me(A(u.label) + " ", 1),
        h("button", {
          onClick: (d) => i(u),
          innerHTML: g(U).x
        }, null, 8, ip)
      ]))), 128)),
      r.value.length > 1 ? (_(), m("button", {
        key: 0,
        class: "pxc-active-filters__clear",
        onClick: o
      }, " Alle löschen ")) : N("", !0)
    ])) : N("", !0);
  }
}, lp = ["innerHTML"], cp = {
  __name: "SidebarToggleWidget",
  setup(e) {
    const { state: t, actions: s } = Se();
    let n = null;
    return St(() => {
      n = document.querySelector("[data-catalog-sidebar]"), n && n.classList.add("pxc-sidebar");
    }), Ae(() => t.sidebarOpen, (r) => {
      n && (r ? (n.classList.add("pxc-sidebar--open"), document.body.style.overflow = "hidden") : (n.classList.remove("pxc-sidebar--open"), document.body.style.overflow = ""));
    }), Ae(() => t.selectedCategoryId, () => {
      t.sidebarOpen && s.closeSidebar();
    }), zs(() => {
      n && n.classList.remove("pxc-sidebar", "pxc-sidebar--open"), document.body.style.overflow = "";
    }), (r, i) => (_(), m(Z, null, [
      h("button", {
        class: "pxc-sidebar-toggle",
        onClick: i[0] || (i[0] = (o) => g(s).toggleSidebar())
      }, [
        h("span", {
          innerHTML: g(U).menu
        }, null, 8, lp)
      ]),
      (_(), ps(Gs, { to: "body" }, [
        ye(Xs, { name: "pxc-fade" }, {
          default: ds(() => [
            g(t).sidebarOpen ? (_(), m("div", {
              key: 0,
              class: "pxc-sidebar-overlay",
              onClick: i[1] || (i[1] = (o) => g(s).closeSidebar())
            })) : N("", !0)
          ]),
          _: 1
        })
      ]))
    ], 64));
  }
}, An = {
  search: ea,
  categories: ya,
  facets: _u,
  "product-grid": Hu,
  pagination: Ku,
  toolbar: sf,
  wishlist: Ef,
  "wishlist-button": Hf,
  "product-detail": Vd,
  compare: ep,
  locale: np,
  "active-filters": op,
  "sidebar-toggle": cp
}, En = [];
function In() {
  document.querySelectorAll("[data-catalog]").forEach((t) => {
    if (t.__pxc_mounted) return;
    const s = t.getAttribute("data-catalog"), n = An[s];
    if (!n) {
      console.warn(`[PublixxCatalog] Unknown widget: "${s}". Available: ${Object.keys(An).join(", ")}`);
      return;
    }
    const r = {};
    for (const o of t.attributes)
      if (o.name.startsWith("data-") && o.name !== "data-catalog") {
        const l = o.name.slice(5).replace(/-([a-z])/g, (c, u) => u.toUpperCase());
        r[l] = o.value;
      }
    const i = Vc({
      render() {
        return ro(n, r);
      }
    });
    i.mount(t), t.__pxc_mounted = !0, En.push({ el: t, app: i });
  });
}
function ap() {
  En.forEach(({ app: e }) => e.unmount()), En.length = 0;
}
async function up(e = {}) {
  Gc({
    baseUrl: e.api || e.baseUrl || "/api/v1",
    token: e.token,
    timeout: e.timeout
  });
  const { state: t, actions: s } = Se();
  e.locale && (t.locale = e.locale), e.perPage && (t.meta.per_page = e.perPage), await s.fetchSettings(), s.importWishlistFromUrl(), await s.applyDeeplinks(), e.autoMount !== !1 && (document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", In) : In());
}
const fp = {
  init: up,
  mount: In,
  destroy: ap,
  store: Se,
  widgets: An,
  version: "1.0.0"
};
typeof window < "u" && (window.PublixxCatalog = fp);
export {
  fp as default,
  ap as destroy,
  up as init,
  In as mount,
  Se as useStore
};
