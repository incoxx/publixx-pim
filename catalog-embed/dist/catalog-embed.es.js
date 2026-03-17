/**
* @vue/shared v3.5.30
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
// @__NO_SIDE_EFFECTS__
function $n(e) {
  const t = /* @__PURE__ */ Object.create(null);
  for (const s of e.split(",")) t[s] = 1;
  return (s) => s in t;
}
const ne = {}, Lt = [], ze = () => {
}, jr = () => !1, Is = (e) => e.charCodeAt(0) === 111 && e.charCodeAt(1) === 110 && // uppercase letter
(e.charCodeAt(2) > 122 || e.charCodeAt(2) < 97), Sn = (e) => e.startsWith("onUpdate:"), ge = Object.assign, Mn = (e, t) => {
  const s = e.indexOf(t);
  s > -1 && e.splice(s, 1);
}, lo = Object.prototype.hasOwnProperty, X = (e, t) => lo.call(e, t), W = Array.isArray, At = (e) => os(e) === "[object Map]", Hs = (e) => os(e) === "[object Set]", Yn = (e) => os(e) === "[object Date]", G = (e) => typeof e == "function", fe = (e) => typeof e == "string", Qe = (e) => typeof e == "symbol", ee = (e) => e !== null && typeof e == "object", Nr = (e) => (ee(e) || G(e)) && G(e.then) && G(e.catch), Vr = Object.prototype.toString, os = (e) => Vr.call(e), co = (e) => os(e).slice(8, -1), Wr = (e) => os(e) === "[object Object]", Pn = (e) => fe(e) && e !== "NaN" && e[0] !== "-" && "" + parseInt(e, 10) === e, Kt = /* @__PURE__ */ $n(
  // the leading comma is intentional so empty string "" is also included
  ",key,ref,ref_for,ref_key,onVnodeBeforeMount,onVnodeMounted,onVnodeBeforeUpdate,onVnodeUpdated,onVnodeBeforeUnmount,onVnodeUnmounted"
), Fs = (e) => {
  const t = /* @__PURE__ */ Object.create(null);
  return ((s) => t[s] || (t[s] = e(s)));
}, ao = /-\w/g, Fe = Fs(
  (e) => e.replace(ao, (t) => t.slice(1).toUpperCase())
), uo = /\B([A-Z])/g, St = Fs(
  (e) => e.replace(uo, "-$1").toLowerCase()
), Br = Fs((e) => e.charAt(0).toUpperCase() + e.slice(1)), qs = Fs(
  (e) => e ? `on${Br(e)}` : ""
), Je = (e, t) => !Object.is(e, t), ms = (e, ...t) => {
  for (let s = 0; s < e.length; s++)
    e[s](...t);
}, Ur = (e, t, s, n = !1) => {
  Object.defineProperty(e, t, {
    configurable: !0,
    enumerable: !1,
    writable: n,
    value: s
  });
}, Ln = (e) => {
  const t = parseFloat(e);
  return isNaN(t) ? e : t;
}, fo = (e) => {
  const t = fe(e) ? Number(e) : NaN;
  return isNaN(t) ? e : t;
};
let Qn;
const Os = () => Qn || (Qn = typeof globalThis < "u" ? globalThis : typeof self < "u" ? self : typeof window < "u" ? window : typeof global < "u" ? global : {});
function An(e) {
  if (W(e)) {
    const t = {};
    for (let s = 0; s < e.length; s++) {
      const n = e[s], r = fe(n) ? _o(n) : An(n);
      if (r)
        for (const i in r)
          t[i] = r[i];
    }
    return t;
  } else if (fe(e) || ee(e))
    return e;
}
const ho = /;(?![^(]*\))/g, po = /:([^]+)/, go = /\/\*[^]*?\*\//g;
function _o(e) {
  const t = {};
  return e.replace(go, "").split(ho).forEach((s) => {
    if (s) {
      const n = s.split(po);
      n.length > 1 && (t[n[0].trim()] = n[1].trim());
    }
  }), t;
}
function pe(e) {
  let t = "";
  if (fe(e))
    t = e;
  else if (W(e))
    for (let s = 0; s < e.length; s++) {
      const n = pe(e[s]);
      n && (t += n + " ");
    }
  else if (ee(e))
    for (const s in e)
      e[s] && (t += s + " ");
  return t.trim();
}
const mo = "itemscope,allowfullscreen,formnovalidate,ismap,nomodule,novalidate,readonly", vo = /* @__PURE__ */ $n(mo);
function Kr(e) {
  return !!e || e === "";
}
function yo(e, t) {
  if (e.length !== t.length) return !1;
  let s = !0;
  for (let n = 0; s && n < e.length; n++)
    s = ls(e[n], t[n]);
  return s;
}
function ls(e, t) {
  if (e === t) return !0;
  let s = Yn(e), n = Yn(t);
  if (s || n)
    return s && n ? e.getTime() === t.getTime() : !1;
  if (s = Qe(e), n = Qe(t), s || n)
    return e === t;
  if (s = W(e), n = W(t), s || n)
    return s && n ? yo(e, t) : !1;
  if (s = ee(e), n = ee(t), s || n) {
    if (!s || !n)
      return !1;
    const r = Object.keys(e).length, i = Object.keys(t).length;
    if (r !== i)
      return !1;
    for (const o in e) {
      const l = e.hasOwnProperty(o), c = t.hasOwnProperty(o);
      if (l && !c || !l && c || !ls(e[o], t[o]))
        return !1;
    }
  }
  return String(e) === String(t);
}
function qr(e, t) {
  return e.findIndex((s) => ls(s, t));
}
const Gr = (e) => !!(e && e.__v_isRef === !0), D = (e) => fe(e) ? e : e == null ? "" : W(e) || ee(e) && (e.toString === Vr || !G(e.toString)) ? Gr(e) ? D(e.value) : JSON.stringify(e, Jr, 2) : String(e), Jr = (e, t) => Gr(t) ? Jr(e, t.value) : At(t) ? {
  [`Map(${t.size})`]: [...t.entries()].reduce(
    (s, [n, r], i) => (s[Gs(n, i) + " =>"] = r, s),
    {}
  )
} : Hs(t) ? {
  [`Set(${t.size})`]: [...t.values()].map((s) => Gs(s))
} : Qe(t) ? Gs(t) : ee(t) && !W(t) && !Wr(t) ? String(t) : t, Gs = (e, t = "") => {
  var s;
  return (
    // Symbol.description in es2019+ so we need to cast here to pass
    // the lib: es2016 check
    Qe(e) ? `Symbol(${(s = e.description) != null ? s : t})` : e
  );
};
/**
* @vue/reactivity v3.5.30
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
let Se;
class xo {
  // TODO isolatedDeclarations "__v_skip"
  constructor(t = !1) {
    this.detached = t, this._active = !0, this._on = 0, this.effects = [], this.cleanups = [], this._isPaused = !1, this.__v_skip = !0, this.parent = Se, !t && Se && (this.index = (Se.scopes || (Se.scopes = [])).push(
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
      const s = Se;
      try {
        return Se = this, t();
      } finally {
        Se = s;
      }
    }
  }
  /**
   * This should only be called on non-detached scopes
   * @internal
   */
  on() {
    ++this._on === 1 && (this.prevScope = Se, Se = this);
  }
  /**
   * This should only be called on non-detached scopes
   * @internal
   */
  off() {
    this._on > 0 && --this._on === 0 && (Se = this.prevScope, this.prevScope = void 0);
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
function bo() {
  return Se;
}
let oe;
const Js = /* @__PURE__ */ new WeakSet();
class zr {
  constructor(t) {
    this.fn = t, this.deps = void 0, this.depsTail = void 0, this.flags = 5, this.next = void 0, this.cleanup = void 0, this.scheduler = void 0, Se && Se.active && Se.effects.push(this);
  }
  pause() {
    this.flags |= 64;
  }
  resume() {
    this.flags & 64 && (this.flags &= -65, Js.has(this) && (Js.delete(this), this.trigger()));
  }
  /**
   * @internal
   */
  notify() {
    this.flags & 2 && !(this.flags & 32) || this.flags & 8 || Yr(this);
  }
  run() {
    if (!(this.flags & 1))
      return this.fn();
    this.flags |= 2, Xn(this), Qr(this);
    const t = oe, s = Oe;
    oe = this, Oe = !0;
    try {
      return this.fn();
    } finally {
      Xr(this), oe = t, Oe = s, this.flags &= -3;
    }
  }
  stop() {
    if (this.flags & 1) {
      for (let t = this.deps; t; t = t.nextDep)
        Hn(t);
      this.deps = this.depsTail = void 0, Xn(this), this.onStop && this.onStop(), this.flags &= -2;
    }
  }
  trigger() {
    this.flags & 64 ? Js.add(this) : this.scheduler ? this.scheduler() : this.runIfDirty();
  }
  /**
   * @internal
   */
  runIfDirty() {
    cn(this) && this.run();
  }
  get dirty() {
    return cn(this);
  }
}
let Zr = 0, qt, Gt;
function Yr(e, t = !1) {
  if (e.flags |= 8, t) {
    e.next = Gt, Gt = e;
    return;
  }
  e.next = qt, qt = e;
}
function En() {
  Zr++;
}
function In() {
  if (--Zr > 0)
    return;
  if (Gt) {
    let t = Gt;
    for (Gt = void 0; t; ) {
      const s = t.next;
      t.next = void 0, t.flags &= -9, t = s;
    }
  }
  let e;
  for (; qt; ) {
    let t = qt;
    for (qt = void 0; t; ) {
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
function Qr(e) {
  for (let t = e.deps; t; t = t.nextDep)
    t.version = -1, t.prevActiveLink = t.dep.activeLink, t.dep.activeLink = t;
}
function Xr(e) {
  let t, s = e.depsTail, n = s;
  for (; n; ) {
    const r = n.prevDep;
    n.version === -1 ? (n === s && (s = r), Hn(n), wo(n)) : t = n, n.dep.activeLink = n.prevActiveLink, n.prevActiveLink = void 0, n = r;
  }
  e.deps = t, e.depsTail = s;
}
function cn(e) {
  for (let t = e.deps; t; t = t.nextDep)
    if (t.dep.version !== t.version || t.dep.computed && (ei(t.dep.computed) || t.dep.version !== t.version))
      return !0;
  return !!e._dirty;
}
function ei(e) {
  if (e.flags & 4 && !(e.flags & 16) || (e.flags &= -17, e.globalVersion === Xt) || (e.globalVersion = Xt, !e.isSSR && e.flags & 128 && (!e.deps && !e._dirty || !cn(e))))
    return;
  e.flags |= 2;
  const t = e.dep, s = oe, n = Oe;
  oe = e, Oe = !0;
  try {
    Qr(e);
    const r = e.fn(e._value);
    (t.version === 0 || Je(r, e._value)) && (e.flags |= 128, e._value = r, t.version++);
  } catch (r) {
    throw t.version++, r;
  } finally {
    oe = s, Oe = n, Xr(e), e.flags &= -3;
  }
}
function Hn(e, t = !1) {
  const { dep: s, prevSub: n, nextSub: r } = e;
  if (n && (n.nextSub = r, e.prevSub = void 0), r && (r.prevSub = n, e.nextSub = void 0), s.subs === e && (s.subs = n, !n && s.computed)) {
    s.computed.flags &= -5;
    for (let i = s.computed.deps; i; i = i.nextDep)
      Hn(i, !0);
  }
  !t && !--s.sc && s.map && s.map.delete(s.key);
}
function wo(e) {
  const { prevDep: t, nextDep: s } = e;
  t && (t.nextDep = s, e.prevDep = void 0), s && (s.prevDep = t, e.nextDep = void 0);
}
let Oe = !0;
const ti = [];
function ct() {
  ti.push(Oe), Oe = !1;
}
function at() {
  const e = ti.pop();
  Oe = e === void 0 ? !0 : e;
}
function Xn(e) {
  const { cleanup: t } = e;
  if (e.cleanup = void 0, t) {
    const s = oe;
    oe = void 0;
    try {
      t();
    } finally {
      oe = s;
    }
  }
}
let Xt = 0;
class Co {
  constructor(t, s) {
    this.sub = t, this.dep = s, this.version = s.version, this.nextDep = this.prevDep = this.nextSub = this.prevSub = this.prevActiveLink = void 0;
  }
}
class Fn {
  // TODO isolatedDeclarations "__v_skip"
  constructor(t) {
    this.computed = t, this.version = 0, this.activeLink = void 0, this.subs = void 0, this.map = void 0, this.key = void 0, this.sc = 0, this.__v_skip = !0;
  }
  track(t) {
    if (!oe || !Oe || oe === this.computed)
      return;
    let s = this.activeLink;
    if (s === void 0 || s.sub !== oe)
      s = this.activeLink = new Co(oe, this), oe.deps ? (s.prevDep = oe.depsTail, oe.depsTail.nextDep = s, oe.depsTail = s) : oe.deps = oe.depsTail = s, si(s);
    else if (s.version === -1 && (s.version = this.version, s.nextDep)) {
      const n = s.nextDep;
      n.prevDep = s.prevDep, s.prevDep && (s.prevDep.nextDep = n), s.prevDep = oe.depsTail, s.nextDep = void 0, oe.depsTail.nextDep = s, oe.depsTail = s, oe.deps === s && (oe.deps = n);
    }
    return s;
  }
  trigger(t) {
    this.version++, Xt++, this.notify(t);
  }
  notify(t) {
    En();
    try {
      for (let s = this.subs; s; s = s.prevSub)
        s.sub.notify() && s.sub.dep.notify();
    } finally {
      In();
    }
  }
}
function si(e) {
  if (e.dep.sc++, e.sub.flags & 4) {
    const t = e.dep.computed;
    if (t && !e.dep.subs) {
      t.flags |= 20;
      for (let n = t.deps; n; n = n.nextDep)
        si(n);
    }
    const s = e.dep.subs;
    s !== e && (e.prevSub = s, s && (s.nextSub = e)), e.dep.subs = e;
  }
}
const an = /* @__PURE__ */ new WeakMap(), Tt = /* @__PURE__ */ Symbol(
  ""
), un = /* @__PURE__ */ Symbol(
  ""
), es = /* @__PURE__ */ Symbol(
  ""
);
function ve(e, t, s) {
  if (Oe && oe) {
    let n = an.get(e);
    n || an.set(e, n = /* @__PURE__ */ new Map());
    let r = n.get(s);
    r || (n.set(s, r = new Fn()), r.map = n, r.key = s), r.track();
  }
}
function ot(e, t, s, n, r, i) {
  const o = an.get(e);
  if (!o) {
    Xt++;
    return;
  }
  const l = (c) => {
    c && c.trigger();
  };
  if (En(), t === "clear")
    o.forEach(l);
  else {
    const c = W(e), u = c && Pn(s);
    if (c && s === "length") {
      const a = Number(n);
      o.forEach((d, _) => {
        (_ === "length" || _ === es || !Qe(_) && _ >= a) && l(d);
      });
    } else
      switch ((s !== void 0 || o.has(void 0)) && l(o.get(s)), u && l(o.get(es)), t) {
        case "add":
          c ? u && l(o.get("length")) : (l(o.get(Tt)), At(e) && l(o.get(un)));
          break;
        case "delete":
          c || (l(o.get(Tt)), At(e) && l(o.get(un)));
          break;
        case "set":
          At(e) && l(o.get(Tt));
          break;
      }
  }
  In();
}
function Mt(e) {
  const t = /* @__PURE__ */ Q(e);
  return t === e ? t : (ve(t, "iterate", es), /* @__PURE__ */ He(e) ? t : t.map(De));
}
function Ds(e) {
  return ve(e = /* @__PURE__ */ Q(e), "iterate", es), e;
}
function qe(e, t) {
  return /* @__PURE__ */ ut(e) ? Ft(/* @__PURE__ */ $t(e) ? De(t) : t) : De(t);
}
const ko = {
  __proto__: null,
  [Symbol.iterator]() {
    return zs(this, Symbol.iterator, (e) => qe(this, e));
  },
  concat(...e) {
    return Mt(this).concat(
      ...e.map((t) => W(t) ? Mt(t) : t)
    );
  },
  entries() {
    return zs(this, "entries", (e) => (e[1] = qe(this, e[1]), e));
  },
  every(e, t) {
    return et(this, "every", e, t, void 0, arguments);
  },
  filter(e, t) {
    return et(
      this,
      "filter",
      e,
      t,
      (s) => s.map((n) => qe(this, n)),
      arguments
    );
  },
  find(e, t) {
    return et(
      this,
      "find",
      e,
      t,
      (s) => qe(this, s),
      arguments
    );
  },
  findIndex(e, t) {
    return et(this, "findIndex", e, t, void 0, arguments);
  },
  findLast(e, t) {
    return et(
      this,
      "findLast",
      e,
      t,
      (s) => qe(this, s),
      arguments
    );
  },
  findLastIndex(e, t) {
    return et(this, "findLastIndex", e, t, void 0, arguments);
  },
  // flat, flatMap could benefit from ARRAY_ITERATE but are not straight-forward to implement
  forEach(e, t) {
    return et(this, "forEach", e, t, void 0, arguments);
  },
  includes(...e) {
    return Zs(this, "includes", e);
  },
  indexOf(...e) {
    return Zs(this, "indexOf", e);
  },
  join(e) {
    return Mt(this).join(e);
  },
  // keys() iterator only reads `length`, no optimization required
  lastIndexOf(...e) {
    return Zs(this, "lastIndexOf", e);
  },
  map(e, t) {
    return et(this, "map", e, t, void 0, arguments);
  },
  pop() {
    return jt(this, "pop");
  },
  push(...e) {
    return jt(this, "push", e);
  },
  reduce(e, ...t) {
    return er(this, "reduce", e, t);
  },
  reduceRight(e, ...t) {
    return er(this, "reduceRight", e, t);
  },
  shift() {
    return jt(this, "shift");
  },
  // slice could use ARRAY_ITERATE but also seems to beg for range tracking
  some(e, t) {
    return et(this, "some", e, t, void 0, arguments);
  },
  splice(...e) {
    return jt(this, "splice", e);
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
    return jt(this, "unshift", e);
  },
  values() {
    return zs(this, "values", (e) => qe(this, e));
  }
};
function zs(e, t, s) {
  const n = Ds(e), r = n[t]();
  return n !== e && !/* @__PURE__ */ He(e) && (r._next = r.next, r.next = () => {
    const i = r._next();
    return i.done || (i.value = s(i.value)), i;
  }), r;
}
const To = Array.prototype;
function et(e, t, s, n, r, i) {
  const o = Ds(e), l = o !== e && !/* @__PURE__ */ He(e), c = o[t];
  if (c !== To[t]) {
    const d = c.apply(e, i);
    return l ? De(d) : d;
  }
  let u = s;
  o !== e && (l ? u = function(d, _) {
    return s.call(this, qe(e, d), _, e);
  } : s.length > 2 && (u = function(d, _) {
    return s.call(this, d, _, e);
  }));
  const a = c.call(o, u, n);
  return l && r ? r(a) : a;
}
function er(e, t, s, n) {
  const r = Ds(e), i = r !== e && !/* @__PURE__ */ He(e);
  let o = s, l = !1;
  r !== e && (i ? (l = n.length === 0, o = function(u, a, d) {
    return l && (l = !1, u = qe(e, u)), s.call(this, u, qe(e, a), d, e);
  }) : s.length > 3 && (o = function(u, a, d) {
    return s.call(this, u, a, d, e);
  }));
  const c = r[t](o, ...n);
  return l ? qe(e, c) : c;
}
function Zs(e, t, s) {
  const n = /* @__PURE__ */ Q(e);
  ve(n, "iterate", es);
  const r = n[t](...s);
  return (r === -1 || r === !1) && /* @__PURE__ */ Rn(s[0]) ? (s[0] = /* @__PURE__ */ Q(s[0]), n[t](...s)) : r;
}
function jt(e, t, s = []) {
  ct(), En();
  const n = (/* @__PURE__ */ Q(e))[t].apply(e, s);
  return In(), at(), n;
}
const $o = /* @__PURE__ */ $n("__proto__,__v_isRef,__isVue"), ni = new Set(
  /* @__PURE__ */ Object.getOwnPropertyNames(Symbol).filter((e) => e !== "arguments" && e !== "caller").map((e) => Symbol[e]).filter(Qe)
);
function So(e) {
  Qe(e) || (e = String(e));
  const t = /* @__PURE__ */ Q(this);
  return ve(t, "has", e), t.hasOwnProperty(e);
}
class ri {
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
      return n === (r ? i ? Do : ci : i ? li : oi).get(t) || // receiver is not the reactive proxy, but has the same prototype
      // this means the receiver is a user proxy of the reactive proxy
      Object.getPrototypeOf(t) === Object.getPrototypeOf(n) ? t : void 0;
    const o = W(t);
    if (!r) {
      let c;
      if (o && (c = ko[s]))
        return c;
      if (s === "hasOwnProperty")
        return So;
    }
    const l = Reflect.get(
      t,
      s,
      // if this is a proxy wrapping a ref, return methods using the raw ref
      // as receiver so that we don't have to call `toRaw` on the ref in all
      // its class methods
      /* @__PURE__ */ ye(t) ? t : n
    );
    if ((Qe(s) ? ni.has(s) : $o(s)) || (r || ve(t, "get", s), i))
      return l;
    if (/* @__PURE__ */ ye(l)) {
      const c = o && Pn(s) ? l : l.value;
      return r && ee(c) ? /* @__PURE__ */ dn(c) : c;
    }
    return ee(l) ? r ? /* @__PURE__ */ dn(l) : /* @__PURE__ */ Rs(l) : l;
  }
}
class ii extends ri {
  constructor(t = !1) {
    super(!1, t);
  }
  set(t, s, n, r) {
    let i = t[s];
    const o = W(t) && Pn(s);
    if (!this._isShallow) {
      const u = /* @__PURE__ */ ut(i);
      if (!/* @__PURE__ */ He(n) && !/* @__PURE__ */ ut(n) && (i = /* @__PURE__ */ Q(i), n = /* @__PURE__ */ Q(n)), !o && /* @__PURE__ */ ye(i) && !/* @__PURE__ */ ye(n))
        return u || (i.value = n), !0;
    }
    const l = o ? Number(s) < t.length : X(t, s), c = Reflect.set(
      t,
      s,
      n,
      /* @__PURE__ */ ye(t) ? t : r
    );
    return t === /* @__PURE__ */ Q(r) && (l ? Je(n, i) && ot(t, "set", s, n) : ot(t, "add", s, n)), c;
  }
  deleteProperty(t, s) {
    const n = X(t, s);
    t[s];
    const r = Reflect.deleteProperty(t, s);
    return r && n && ot(t, "delete", s, void 0), r;
  }
  has(t, s) {
    const n = Reflect.has(t, s);
    return (!Qe(s) || !ni.has(s)) && ve(t, "has", s), n;
  }
  ownKeys(t) {
    return ve(
      t,
      "iterate",
      W(t) ? "length" : Tt
    ), Reflect.ownKeys(t);
  }
}
class Mo extends ri {
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
const Po = /* @__PURE__ */ new ii(), Lo = /* @__PURE__ */ new Mo(), Ao = /* @__PURE__ */ new ii(!0);
const fn = (e) => e, hs = (e) => Reflect.getPrototypeOf(e);
function Eo(e, t, s) {
  return function(...n) {
    const r = this.__v_raw, i = /* @__PURE__ */ Q(r), o = At(i), l = e === "entries" || e === Symbol.iterator && o, c = e === "keys" && o, u = r[e](...n), a = s ? fn : t ? Ft : De;
    return !t && ve(
      i,
      "iterate",
      c ? un : Tt
    ), ge(
      // inheriting all iterator properties
      Object.create(u),
      {
        // iterator protocol
        next() {
          const { value: d, done: _ } = u.next();
          return _ ? { value: d, done: _ } : {
            value: l ? [a(d[0]), a(d[1])] : a(d),
            done: _
          };
        }
      }
    );
  };
}
function ps(e) {
  return function(...t) {
    return e === "delete" ? !1 : e === "clear" ? void 0 : this;
  };
}
function Io(e, t) {
  const s = {
    get(r) {
      const i = this.__v_raw, o = /* @__PURE__ */ Q(i), l = /* @__PURE__ */ Q(r);
      e || (Je(r, l) && ve(o, "get", r), ve(o, "get", l));
      const { has: c } = hs(o), u = t ? fn : e ? Ft : De;
      if (c.call(o, r))
        return u(i.get(r));
      if (c.call(o, l))
        return u(i.get(l));
      i !== o && i.get(r);
    },
    get size() {
      const r = this.__v_raw;
      return !e && ve(/* @__PURE__ */ Q(r), "iterate", Tt), r.size;
    },
    has(r) {
      const i = this.__v_raw, o = /* @__PURE__ */ Q(i), l = /* @__PURE__ */ Q(r);
      return e || (Je(r, l) && ve(o, "has", r), ve(o, "has", l)), r === l ? i.has(r) : i.has(r) || i.has(l);
    },
    forEach(r, i) {
      const o = this, l = o.__v_raw, c = /* @__PURE__ */ Q(l), u = t ? fn : e ? Ft : De;
      return !e && ve(c, "iterate", Tt), l.forEach((a, d) => r.call(i, u(a), u(d), o));
    }
  };
  return ge(
    s,
    e ? {
      add: ps("add"),
      set: ps("set"),
      delete: ps("delete"),
      clear: ps("clear")
    } : {
      add(r) {
        const i = /* @__PURE__ */ Q(this), o = hs(i), l = /* @__PURE__ */ Q(r), c = !t && !/* @__PURE__ */ He(r) && !/* @__PURE__ */ ut(r) ? l : r;
        return o.has.call(i, c) || Je(r, c) && o.has.call(i, r) || Je(l, c) && o.has.call(i, l) || (i.add(c), ot(i, "add", c, c)), this;
      },
      set(r, i) {
        !t && !/* @__PURE__ */ He(i) && !/* @__PURE__ */ ut(i) && (i = /* @__PURE__ */ Q(i));
        const o = /* @__PURE__ */ Q(this), { has: l, get: c } = hs(o);
        let u = l.call(o, r);
        u || (r = /* @__PURE__ */ Q(r), u = l.call(o, r));
        const a = c.call(o, r);
        return o.set(r, i), u ? Je(i, a) && ot(o, "set", r, i) : ot(o, "add", r, i), this;
      },
      delete(r) {
        const i = /* @__PURE__ */ Q(this), { has: o, get: l } = hs(i);
        let c = o.call(i, r);
        c || (r = /* @__PURE__ */ Q(r), c = o.call(i, r)), l && l.call(i, r);
        const u = i.delete(r);
        return c && ot(i, "delete", r, void 0), u;
      },
      clear() {
        const r = /* @__PURE__ */ Q(this), i = r.size !== 0, o = r.clear();
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
    s[r] = Eo(r, e, t);
  }), s;
}
function On(e, t) {
  const s = Io(e, t);
  return (n, r, i) => r === "__v_isReactive" ? !e : r === "__v_isReadonly" ? e : r === "__v_raw" ? n : Reflect.get(
    X(s, r) && r in n ? s : n,
    r,
    i
  );
}
const Ho = {
  get: /* @__PURE__ */ On(!1, !1)
}, Fo = {
  get: /* @__PURE__ */ On(!1, !0)
}, Oo = {
  get: /* @__PURE__ */ On(!0, !1)
};
const oi = /* @__PURE__ */ new WeakMap(), li = /* @__PURE__ */ new WeakMap(), ci = /* @__PURE__ */ new WeakMap(), Do = /* @__PURE__ */ new WeakMap();
function Ro(e) {
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
function jo(e) {
  return e.__v_skip || !Object.isExtensible(e) ? 0 : Ro(co(e));
}
// @__NO_SIDE_EFFECTS__
function Rs(e) {
  return /* @__PURE__ */ ut(e) ? e : Dn(
    e,
    !1,
    Po,
    Ho,
    oi
  );
}
// @__NO_SIDE_EFFECTS__
function No(e) {
  return Dn(
    e,
    !1,
    Ao,
    Fo,
    li
  );
}
// @__NO_SIDE_EFFECTS__
function dn(e) {
  return Dn(
    e,
    !0,
    Lo,
    Oo,
    ci
  );
}
function Dn(e, t, s, n, r) {
  if (!ee(e) || e.__v_raw && !(t && e.__v_isReactive))
    return e;
  const i = jo(e);
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
function He(e) {
  return !!(e && e.__v_isShallow);
}
// @__NO_SIDE_EFFECTS__
function Rn(e) {
  return e ? !!e.__v_raw : !1;
}
// @__NO_SIDE_EFFECTS__
function Q(e) {
  const t = e && e.__v_raw;
  return t ? /* @__PURE__ */ Q(t) : e;
}
function Vo(e) {
  return !X(e, "__v_skip") && Object.isExtensible(e) && Ur(e, "__v_skip", !0), e;
}
const De = (e) => ee(e) ? /* @__PURE__ */ Rs(e) : e, Ft = (e) => ee(e) ? /* @__PURE__ */ dn(e) : e;
// @__NO_SIDE_EFFECTS__
function ye(e) {
  return e ? e.__v_isRef === !0 : !1;
}
// @__NO_SIDE_EFFECTS__
function Ze(e) {
  return Wo(e, !1);
}
function Wo(e, t) {
  return /* @__PURE__ */ ye(e) ? e : new Bo(e, t);
}
class Bo {
  constructor(t, s) {
    this.dep = new Fn(), this.__v_isRef = !0, this.__v_isShallow = !1, this._rawValue = s ? t : /* @__PURE__ */ Q(t), this._value = s ? t : De(t), this.__v_isShallow = s;
  }
  get value() {
    return this.dep.track(), this._value;
  }
  set value(t) {
    const s = this._rawValue, n = this.__v_isShallow || /* @__PURE__ */ He(t) || /* @__PURE__ */ ut(t);
    t = n ? t : /* @__PURE__ */ Q(t), Je(t, s) && (this._rawValue = t, this._value = n ? t : De(t), this.dep.trigger());
  }
}
function p(e) {
  return /* @__PURE__ */ ye(e) ? e.value : e;
}
const Uo = {
  get: (e, t, s) => t === "__v_raw" ? e : p(Reflect.get(e, t, s)),
  set: (e, t, s, n) => {
    const r = e[t];
    return /* @__PURE__ */ ye(r) && !/* @__PURE__ */ ye(s) ? (r.value = s, !0) : Reflect.set(e, t, s, n);
  }
};
function ai(e) {
  return /* @__PURE__ */ $t(e) ? e : new Proxy(e, Uo);
}
class Ko {
  constructor(t, s, n) {
    this.fn = t, this.setter = s, this._value = void 0, this.dep = new Fn(this), this.__v_isRef = !0, this.deps = void 0, this.depsTail = void 0, this.flags = 16, this.globalVersion = Xt - 1, this.next = void 0, this.effect = this, this.__v_isReadonly = !s, this.isSSR = n;
  }
  /**
   * @internal
   */
  notify() {
    if (this.flags |= 16, !(this.flags & 8) && // avoid infinite self recursion
    oe !== this)
      return Yr(this, !0), !0;
  }
  get value() {
    const t = this.dep.track();
    return ei(this), t && (t.version = this.dep.version), this._value;
  }
  set value(t) {
    this.setter && this.setter(t);
  }
}
// @__NO_SIDE_EFFECTS__
function qo(e, t, s = !1) {
  let n, r;
  return G(e) ? n = e : (n = e.get, r = e.set), new Ko(n, r, s);
}
const gs = {}, Cs = /* @__PURE__ */ new WeakMap();
let wt;
function Go(e, t = !1, s = wt) {
  if (s) {
    let n = Cs.get(s);
    n || Cs.set(s, n = []), n.push(e);
  }
}
function Jo(e, t, s = ne) {
  const { immediate: n, deep: r, once: i, scheduler: o, augmentJob: l, call: c } = s, u = (R) => r ? R : /* @__PURE__ */ He(R) || r === !1 || r === 0 ? lt(R, 1) : lt(R);
  let a, d, _, k, F = !1, E = !1;
  if (/* @__PURE__ */ ye(e) ? (d = () => e.value, F = /* @__PURE__ */ He(e)) : /* @__PURE__ */ $t(e) ? (d = () => u(e), F = !0) : W(e) ? (E = !0, F = e.some((R) => /* @__PURE__ */ $t(R) || /* @__PURE__ */ He(R)), d = () => e.map((R) => {
    if (/* @__PURE__ */ ye(R))
      return R.value;
    if (/* @__PURE__ */ $t(R))
      return u(R);
    if (G(R))
      return c ? c(R, 2) : R();
  })) : G(e) ? t ? d = c ? () => c(e, 2) : e : d = () => {
    if (_) {
      ct();
      try {
        _();
      } finally {
        at();
      }
    }
    const R = wt;
    wt = a;
    try {
      return c ? c(e, 3, [k]) : e(k);
    } finally {
      wt = R;
    }
  } : d = ze, t && r) {
    const R = d, Z = r === !0 ? 1 / 0 : r;
    d = () => lt(R(), Z);
  }
  const j = bo(), P = () => {
    a.stop(), j && j.active && Mn(j.effects, a);
  };
  if (i && t) {
    const R = t;
    t = (...Z) => {
      R(...Z), P();
    };
  }
  let T = E ? new Array(e.length).fill(gs) : gs;
  const z = (R) => {
    if (!(!(a.flags & 1) || !a.dirty && !R))
      if (t) {
        const Z = a.run();
        if (r || F || (E ? Z.some((te, ce) => Je(te, T[ce])) : Je(Z, T))) {
          _ && _();
          const te = wt;
          wt = a;
          try {
            const ce = [
              Z,
              // pass undefined as the old value when it's changed for the first time
              T === gs ? void 0 : E && T[0] === gs ? [] : T,
              k
            ];
            T = Z, c ? c(t, 3, ce) : (
              // @ts-expect-error
              t(...ce)
            );
          } finally {
            wt = te;
          }
        }
      } else
        a.run();
  };
  return l && l(z), a = new zr(d), a.scheduler = o ? () => o(z, !1) : z, k = (R) => Go(R, !1, a), _ = a.onStop = () => {
    const R = Cs.get(a);
    if (R) {
      if (c)
        c(R, 4);
      else
        for (const Z of R) Z();
      Cs.delete(a);
    }
  }, t ? n ? z(!0) : T = a.run() : o ? o(z.bind(null, !0), !0) : a.run(), P.pause = a.pause.bind(a), P.resume = a.resume.bind(a), P.stop = P, P;
}
function lt(e, t = 1 / 0, s) {
  if (t <= 0 || !ee(e) || e.__v_skip || (s = s || /* @__PURE__ */ new Map(), (s.get(e) || 0) >= t))
    return e;
  if (s.set(e, t), t--, /* @__PURE__ */ ye(e))
    lt(e.value, t, s);
  else if (W(e))
    for (let n = 0; n < e.length; n++)
      lt(e[n], t, s);
  else if (Hs(e) || At(e))
    e.forEach((n) => {
      lt(n, t, s);
    });
  else if (Wr(e)) {
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
function cs(e, t, s, n) {
  try {
    return n ? e(...n) : e();
  } catch (r) {
    js(r, t, s);
  }
}
function Re(e, t, s, n) {
  if (G(e)) {
    const r = cs(e, t, s, n);
    return r && Nr(r) && r.catch((i) => {
      js(i, t, s);
    }), r;
  }
  if (W(e)) {
    const r = [];
    for (let i = 0; i < e.length; i++)
      r.push(Re(e[i], t, s, n));
    return r;
  }
}
function js(e, t, s, n = !0) {
  const r = t ? t.vnode : null, { errorHandler: i, throwUnhandledErrorInProduction: o } = t && t.appContext.config || ne;
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
      ct(), cs(i, null, 10, [
        e,
        c,
        u
      ]), at();
      return;
    }
  }
  zo(e, s, r, n, o);
}
function zo(e, t, s, n = !0, r = !1) {
  if (r)
    throw e;
  console.error(e);
}
const be = [];
let Ue = -1;
const Et = [];
let ht = null, Pt = 0;
const ui = /* @__PURE__ */ Promise.resolve();
let ks = null;
function Zo(e) {
  const t = ks || ui;
  return e ? t.then(this ? e.bind(this) : e) : t;
}
function Yo(e) {
  let t = Ue + 1, s = be.length;
  for (; t < s; ) {
    const n = t + s >>> 1, r = be[n], i = ts(r);
    i < e || i === e && r.flags & 2 ? t = n + 1 : s = n;
  }
  return t;
}
function jn(e) {
  if (!(e.flags & 1)) {
    const t = ts(e), s = be[be.length - 1];
    !s || // fast path when the job id is larger than the tail
    !(e.flags & 2) && t >= ts(s) ? be.push(e) : be.splice(Yo(t), 0, e), e.flags |= 1, fi();
  }
}
function fi() {
  ks || (ks = ui.then(hi));
}
function Qo(e) {
  W(e) ? Et.push(...e) : ht && e.id === -1 ? ht.splice(Pt + 1, 0, e) : e.flags & 1 || (Et.push(e), e.flags |= 1), fi();
}
function tr(e, t, s = Ue + 1) {
  for (; s < be.length; s++) {
    const n = be[s];
    if (n && n.flags & 2) {
      if (e && n.id !== e.uid)
        continue;
      be.splice(s, 1), s--, n.flags & 4 && (n.flags &= -2), n(), n.flags & 4 || (n.flags &= -2);
    }
  }
}
function di(e) {
  if (Et.length) {
    const t = [...new Set(Et)].sort(
      (s, n) => ts(s) - ts(n)
    );
    if (Et.length = 0, ht) {
      ht.push(...t);
      return;
    }
    for (ht = t, Pt = 0; Pt < ht.length; Pt++) {
      const s = ht[Pt];
      s.flags & 4 && (s.flags &= -2), s.flags & 8 || s(), s.flags &= -2;
    }
    ht = null, Pt = 0;
  }
}
const ts = (e) => e.id == null ? e.flags & 2 ? -1 : 1 / 0 : e.id;
function hi(e) {
  try {
    for (Ue = 0; Ue < be.length; Ue++) {
      const t = be[Ue];
      t && !(t.flags & 8) && (t.flags & 4 && (t.flags &= -2), cs(
        t,
        t.i,
        t.i ? 15 : 14
      ), t.flags & 4 || (t.flags &= -2));
    }
  } finally {
    for (; Ue < be.length; Ue++) {
      const t = be[Ue];
      t && (t.flags &= -2);
    }
    Ue = -1, be.length = 0, di(), ks = null, (be.length || Et.length) && hi();
  }
}
let Ie = null, pi = null;
function Ts(e) {
  const t = Ie;
  return Ie = e, pi = e && e.type.__scopeId || null, t;
}
function Nn(e, t = Ie, s) {
  if (!t || e._n)
    return e;
  const n = (...r) => {
    n._d && Ms(-1);
    const i = Ts(t);
    let o;
    try {
      o = e(...r);
    } finally {
      Ts(i), n._d && Ms(1);
    }
    return o;
  };
  return n._n = !0, n._c = !0, n._d = !0, n;
}
function hn(e, t) {
  if (Ie === null)
    return e;
  const s = Us(Ie), n = e.dirs || (e.dirs = []);
  for (let r = 0; r < t.length; r++) {
    let [i, o, l, c = ne] = t[r];
    i && (G(i) && (i = {
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
function vt(e, t, s, n) {
  const r = e.dirs, i = t && t.dirs;
  for (let o = 0; o < r.length; o++) {
    const l = r[o];
    i && (l.oldValue = i[o].value);
    let c = l.dir[n];
    c && (ct(), Re(c, s, 8, [
      e.el,
      l,
      e,
      t
    ]), at());
  }
}
function Xo(e, t) {
  if (Ce) {
    let s = Ce.provides;
    const n = Ce.parent && Ce.parent.provides;
    n === s && (s = Ce.provides = Object.create(n)), s[e] = t;
  }
}
function vs(e, t, s = !1) {
  const n = Ji();
  if (n || It) {
    let r = It ? It._context.provides : n ? n.parent == null || n.ce ? n.vnode.appContext && n.vnode.appContext.provides : n.parent.provides : void 0;
    if (r && e in r)
      return r[e];
    if (arguments.length > 1)
      return s && G(t) ? t.call(n && n.proxy) : t;
  }
}
const el = /* @__PURE__ */ Symbol.for("v-scx"), tl = () => vs(el);
function Ye(e, t, s) {
  return gi(e, t, s);
}
function gi(e, t, s = ne) {
  const { immediate: n, deep: r, flush: i, once: o } = s, l = ge({}, s), c = t && n || !t && i !== "post";
  let u;
  if (rs) {
    if (i === "sync") {
      const k = tl();
      u = k.__watcherHandles || (k.__watcherHandles = []);
    } else if (!c) {
      const k = () => {
      };
      return k.stop = ze, k.resume = ze, k.pause = ze, k;
    }
  }
  const a = Ce;
  l.call = (k, F, E) => Re(k, a, F, E);
  let d = !1;
  i === "post" ? l.scheduler = (k) => {
    me(k, a && a.suspense);
  } : i !== "sync" && (d = !0, l.scheduler = (k, F) => {
    F ? k() : jn(k);
  }), l.augmentJob = (k) => {
    t && (k.flags |= 4), d && (k.flags |= 2, a && (k.id = a.uid, k.i = a));
  };
  const _ = Jo(e, t, l);
  return rs && (u ? u.push(_) : c && _()), _;
}
function sl(e, t, s) {
  const n = this.proxy, r = fe(e) ? e.includes(".") ? _i(n, e) : () => n[e] : e.bind(n, n);
  let i;
  G(t) ? i = t : (i = t.handler, s = t);
  const o = us(this), l = gi(r, i.bind(n), s);
  return o(), l;
}
function _i(e, t) {
  const s = t.split(".");
  return () => {
    let n = e;
    for (let r = 0; r < s.length && n; r++)
      n = n[s[r]];
    return n;
  };
}
const mi = /* @__PURE__ */ Symbol("_vte"), vi = (e) => e.__isTeleport, Jt = (e) => e && (e.disabled || e.disabled === ""), sr = (e) => e && (e.defer || e.defer === ""), nr = (e) => typeof SVGElement < "u" && e instanceof SVGElement, rr = (e) => typeof MathMLElement == "function" && e instanceof MathMLElement, pn = (e, t) => {
  const s = e && e.to;
  return fe(s) ? t ? t(s) : null : s;
}, yi = {
  name: "Teleport",
  __isTeleport: !0,
  process(e, t, s, n, r, i, o, l, c, u) {
    const {
      mc: a,
      pc: d,
      pbc: _,
      o: { insert: k, querySelector: F, createText: E, createComment: j }
    } = u, P = Jt(t.props);
    let { shapeFlag: T, children: z, dynamicChildren: R } = t;
    if (e == null) {
      const Z = t.el = E(""), te = t.anchor = E("");
      k(Z, s, n), k(te, s, n);
      const ce = ($, S) => {
        T & 16 && a(
          z,
          $,
          S,
          r,
          i,
          o,
          l,
          c
        );
      }, H = () => {
        const $ = t.target = pn(t.props, F), S = gn($, t, E, k);
        $ && (o !== "svg" && nr($) ? o = "svg" : o !== "mathml" && rr($) && (o = "mathml"), r && r.isCE && (r.ce._teleportTargets || (r.ce._teleportTargets = /* @__PURE__ */ new Set())).add($), P || (ce($, S), ys(t, !1)));
      };
      P && (ce(s, te), ys(t, !0)), sr(t.props) ? (t.el.__isMounted = !1, me(() => {
        H(), delete t.el.__isMounted;
      }, i)) : H();
    } else {
      if (sr(t.props) && e.el.__isMounted === !1) {
        me(() => {
          yi.process(
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
      const Z = t.anchor = e.anchor, te = t.target = e.target, ce = t.targetAnchor = e.targetAnchor, H = Jt(e.props), $ = H ? s : te, S = H ? Z : ce;
      if (o === "svg" || nr(te) ? o = "svg" : (o === "mathml" || rr(te)) && (o = "mathml"), R ? (_(
        e.dynamicChildren,
        R,
        $,
        r,
        i,
        o,
        l
      ), Bn(e, t, !0)) : c || d(
        e,
        t,
        $,
        S,
        r,
        i,
        o,
        l,
        !1
      ), P)
        H ? t.props && e.props && t.props.to !== e.props.to && (t.props.to = e.props.to) : _s(
          t,
          s,
          Z,
          u,
          1
        );
      else if ((t.props && t.props.to) !== (e.props && e.props.to)) {
        const B = t.target = pn(
          t.props,
          F
        );
        B && _s(
          t,
          B,
          null,
          u,
          0
        );
      } else H && _s(
        t,
        te,
        ce,
        u,
        1
      );
      ys(t, P);
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
      props: _
    } = e;
    if (d && (r(u), r(a)), i && r(c), o & 16) {
      const k = i || !Jt(_);
      for (let F = 0; F < l.length; F++) {
        const E = l[F];
        n(
          E,
          t,
          s,
          k,
          !!E.dynamicChildren
        );
      }
    }
  },
  move: _s,
  hydrate: nl
};
function _s(e, t, s, { o: { insert: n }, m: r }, i = 2) {
  i === 0 && n(e.targetAnchor, t, s);
  const { el: o, anchor: l, shapeFlag: c, children: u, props: a } = e, d = i === 2;
  if (d && n(o, t, s), (!d || Jt(a)) && c & 16)
    for (let _ = 0; _ < u.length; _++)
      r(
        u[_],
        t,
        s,
        2
      );
  d && n(l, t, s);
}
function nl(e, t, s, n, r, i, {
  o: { nextSibling: o, parentNode: l, querySelector: c, insert: u, createText: a }
}, d) {
  function _(j, P) {
    let T = P;
    for (; T; ) {
      if (T && T.nodeType === 8) {
        if (T.data === "teleport start anchor")
          t.targetStart = T;
        else if (T.data === "teleport anchor") {
          t.targetAnchor = T, j._lpa = t.targetAnchor && o(t.targetAnchor);
          break;
        }
      }
      T = o(T);
    }
  }
  function k(j, P) {
    P.anchor = d(
      o(j),
      P,
      l(j),
      s,
      n,
      r,
      i
    );
  }
  const F = t.target = pn(
    t.props,
    c
  ), E = Jt(t.props);
  if (F) {
    const j = F._lpa || F.firstChild;
    t.shapeFlag & 16 && (E ? (k(e, t), _(F, j), t.targetAnchor || gn(
      F,
      t,
      a,
      u,
      // if target is the same as the main view, insert anchors before current node
      // to avoid hydrating mismatch
      l(e) === F ? e : null
    )) : (t.anchor = o(e), _(F, j), t.targetAnchor || gn(F, t, a, u), d(
      j && o(j),
      t,
      F,
      s,
      n,
      r,
      i
    ))), ys(t, E);
  } else E && t.shapeFlag & 16 && (k(e, t), t.targetStart = e, t.targetAnchor = o(e));
  return t.anchor && o(t.anchor);
}
const xi = yi;
function ys(e, t) {
  const s = e.ctx;
  if (s && s.ut) {
    let n, r;
    for (t ? (n = e.el, r = e.anchor) : (n = e.targetStart, r = e.targetAnchor); n && n !== r; )
      n.nodeType === 1 && n.setAttribute("data-v-owner", s.uid), n = n.nextSibling;
    s.ut();
  }
}
function gn(e, t, s, n, r = null) {
  const i = t.targetStart = s(""), o = t.targetAnchor = s("");
  return i[mi] = o, e && (n(i, e, r), n(o, e, r)), o;
}
const Ke = /* @__PURE__ */ Symbol("_leaveCb"), Nt = /* @__PURE__ */ Symbol("_enterCb");
function rl() {
  const e = {
    isMounted: !1,
    isLeaving: !1,
    isUnmounting: !1,
    leavingVNodes: /* @__PURE__ */ new Map()
  };
  return as(() => {
    e.isMounted = !0;
  }), Mi(() => {
    e.isUnmounting = !0;
  }), e;
}
const Ee = [Function, Array], bi = {
  mode: String,
  appear: Boolean,
  persisted: Boolean,
  // enter
  onBeforeEnter: Ee,
  onEnter: Ee,
  onAfterEnter: Ee,
  onEnterCancelled: Ee,
  // leave
  onBeforeLeave: Ee,
  onLeave: Ee,
  onAfterLeave: Ee,
  onLeaveCancelled: Ee,
  // appear
  onBeforeAppear: Ee,
  onAppear: Ee,
  onAfterAppear: Ee,
  onAppearCancelled: Ee
}, wi = (e) => {
  const t = e.subTree;
  return t.component ? wi(t.component) : t;
}, il = {
  name: "BaseTransition",
  props: bi,
  setup(e, { slots: t }) {
    const s = Ji(), n = rl();
    return () => {
      const r = t.default && Ti(t.default(), !0);
      if (!r || !r.length)
        return;
      const i = Ci(r), o = /* @__PURE__ */ Q(e), { mode: l } = o;
      if (n.isLeaving)
        return Ys(i);
      const c = ir(i);
      if (!c)
        return Ys(i);
      let u = _n(
        c,
        o,
        n,
        s,
        // #11061, ensure enterHooks is fresh after clone
        (d) => u = d
      );
      c.type !== we && ss(c, u);
      let a = s.subTree && ir(s.subTree);
      if (a && a.type !== we && !Ct(a, c) && wi(s).type !== we) {
        let d = _n(
          a,
          o,
          n,
          s
        );
        if (ss(a, d), l === "out-in" && c.type !== we)
          return n.isLeaving = !0, d.afterLeave = () => {
            n.isLeaving = !1, s.job.flags & 8 || s.update(), delete d.afterLeave, a = void 0;
          }, Ys(i);
        l === "in-out" && c.type !== we ? d.delayLeave = (_, k, F) => {
          const E = ki(
            n,
            a
          );
          E[String(a.key)] = a, _[Ke] = () => {
            k(), _[Ke] = void 0, delete u.delayedLeave, a = void 0;
          }, u.delayedLeave = () => {
            F(), delete u.delayedLeave, a = void 0;
          };
        } : a = void 0;
      } else a && (a = void 0);
      return i;
    };
  }
};
function Ci(e) {
  let t = e[0];
  if (e.length > 1) {
    for (const s of e)
      if (s.type !== we) {
        t = s;
        break;
      }
  }
  return t;
}
const ol = il;
function ki(e, t) {
  const { leavingVNodes: s } = e;
  let n = s.get(t.type);
  return n || (n = /* @__PURE__ */ Object.create(null), s.set(t.type, n)), n;
}
function _n(e, t, s, n, r) {
  const {
    appear: i,
    mode: o,
    persisted: l = !1,
    onBeforeEnter: c,
    onEnter: u,
    onAfterEnter: a,
    onEnterCancelled: d,
    onBeforeLeave: _,
    onLeave: k,
    onAfterLeave: F,
    onLeaveCancelled: E,
    onBeforeAppear: j,
    onAppear: P,
    onAfterAppear: T,
    onAppearCancelled: z
  } = t, R = String(e.key), Z = ki(s, e), te = ($, S) => {
    $ && Re(
      $,
      n,
      9,
      S
    );
  }, ce = ($, S) => {
    const B = S[1];
    te($, S), W($) ? $.every((O) => O.length <= 1) && B() : $.length <= 1 && B();
  }, H = {
    mode: o,
    persisted: l,
    beforeEnter($) {
      let S = c;
      if (!s.isMounted)
        if (i)
          S = j || c;
        else
          return;
      $[Ke] && $[Ke](
        !0
        /* cancelled */
      );
      const B = Z[R];
      B && Ct(e, B) && B.el[Ke] && B.el[Ke](), te(S, [$]);
    },
    enter($) {
      if (Z[R] === e) return;
      let S = u, B = a, O = d;
      if (!s.isMounted)
        if (i)
          S = P || u, B = T || a, O = z || d;
        else
          return;
      let le = !1;
      $[Nt] = (Xe) => {
        le || (le = !0, Xe ? te(O, [$]) : te(B, [$]), H.delayedLeave && H.delayedLeave(), $[Nt] = void 0);
      };
      const _e = $[Nt].bind(null, !1);
      S ? ce(S, [$, _e]) : _e();
    },
    leave($, S) {
      const B = String(e.key);
      if ($[Nt] && $[Nt](
        !0
        /* cancelled */
      ), s.isUnmounting)
        return S();
      te(_, [$]);
      let O = !1;
      $[Ke] = (_e) => {
        O || (O = !0, S(), _e ? te(E, [$]) : te(F, [$]), $[Ke] = void 0, Z[B] === e && delete Z[B]);
      };
      const le = $[Ke].bind(null, !1);
      Z[B] = e, k ? ce(k, [$, le]) : le();
    },
    clone($) {
      const S = _n(
        $,
        t,
        s,
        n,
        r
      );
      return r && r(S), S;
    }
  };
  return H;
}
function Ys(e) {
  if (Ns(e))
    return e = gt(e), e.children = null, e;
}
function ir(e) {
  if (!Ns(e))
    return vi(e.type) && e.children ? Ci(e.children) : e;
  if (e.component)
    return e.component.subTree;
  const { shapeFlag: t, children: s } = e;
  if (s) {
    if (t & 16)
      return s[0];
    if (t & 32 && G(s.default))
      return s.default();
  }
}
function ss(e, t) {
  e.shapeFlag & 6 && e.component ? (e.transition = t, ss(e.component.subTree, t)) : e.shapeFlag & 128 ? (e.ssContent.transition = t.clone(e.ssContent), e.ssFallback.transition = t.clone(e.ssFallback)) : e.transition = t;
}
function Ti(e, t = !1, s) {
  let n = [], r = 0;
  for (let i = 0; i < e.length; i++) {
    let o = e[i];
    const l = s == null ? o.key : String(s) + String(o.key != null ? o.key : i);
    o.type === J ? (o.patchFlag & 128 && r++, n = n.concat(
      Ti(o.children, t, l)
    )) : (t || o.type !== we) && n.push(l != null ? gt(o, { key: l }) : o);
  }
  if (r > 1)
    for (let i = 0; i < n.length; i++)
      n[i].patchFlag = -2;
  return n;
}
function $i(e) {
  e.ids = [e.ids[0] + e.ids[2]++ + "-", 0, 0];
}
function or(e, t) {
  let s;
  return !!((s = Object.getOwnPropertyDescriptor(e, t)) && !s.configurable);
}
const $s = /* @__PURE__ */ new WeakMap();
function zt(e, t, s, n, r = !1) {
  if (W(e)) {
    e.forEach(
      (E, j) => zt(
        E,
        t && (W(t) ? t[j] : t),
        s,
        n,
        r
      )
    );
    return;
  }
  if (Zt(n) && !r) {
    n.shapeFlag & 512 && n.type.__asyncResolved && n.component.subTree.component && zt(e, t, s, n.component.subTree);
    return;
  }
  const i = n.shapeFlag & 4 ? Us(n.component) : n.el, o = r ? null : i, { i: l, r: c } = e, u = t && t.r, a = l.refs === ne ? l.refs = {} : l.refs, d = l.setupState, _ = /* @__PURE__ */ Q(d), k = d === ne ? jr : (E) => or(a, E) ? !1 : X(_, E), F = (E, j) => !(j && or(a, j));
  if (u != null && u !== c) {
    if (lr(t), fe(u))
      a[u] = null, k(u) && (d[u] = null);
    else if (/* @__PURE__ */ ye(u)) {
      const E = t;
      F(u, E.k) && (u.value = null), E.k && (a[E.k] = null);
    }
  }
  if (G(c))
    cs(c, l, 12, [o, a]);
  else {
    const E = fe(c), j = /* @__PURE__ */ ye(c);
    if (E || j) {
      const P = () => {
        if (e.f) {
          const T = E ? k(c) ? d[c] : a[c] : F() || !e.k ? c.value : a[e.k];
          if (r)
            W(T) && Mn(T, i);
          else if (W(T))
            T.includes(i) || T.push(i);
          else if (E)
            a[c] = [i], k(c) && (d[c] = a[c]);
          else {
            const z = [i];
            F(c, e.k) && (c.value = z), e.k && (a[e.k] = z);
          }
        } else E ? (a[c] = o, k(c) && (d[c] = o)) : j && (F(c, e.k) && (c.value = o), e.k && (a[e.k] = o));
      };
      if (o) {
        const T = () => {
          P(), $s.delete(e);
        };
        T.id = -1, $s.set(e, T), me(T, s);
      } else
        lr(e), P();
    }
  }
}
function lr(e) {
  const t = $s.get(e);
  t && (t.flags |= 8, $s.delete(e));
}
Os().requestIdleCallback;
Os().cancelIdleCallback;
const Zt = (e) => !!e.type.__asyncLoader, Ns = (e) => e.type.__isKeepAlive;
function ll(e, t) {
  Si(e, "a", t);
}
function cl(e, t) {
  Si(e, "da", t);
}
function Si(e, t, s = Ce) {
  const n = e.__wdc || (e.__wdc = () => {
    let r = s;
    for (; r; ) {
      if (r.isDeactivated)
        return;
      r = r.parent;
    }
    return e();
  });
  if (Vs(t, n, s), s) {
    let r = s.parent;
    for (; r && r.parent; )
      Ns(r.parent.vnode) && al(n, t, s, r), r = r.parent;
  }
}
function al(e, t, s, n) {
  const r = Vs(
    t,
    e,
    n,
    !0
    /* prepend */
  );
  Pi(() => {
    Mn(n[t], r);
  }, s);
}
function Vs(e, t, s = Ce, n = !1) {
  if (s) {
    const r = s[e] || (s[e] = []), i = t.__weh || (t.__weh = (...o) => {
      ct();
      const l = us(s), c = Re(t, s, e, o);
      return l(), at(), c;
    });
    return n ? r.unshift(i) : r.push(i), i;
  }
}
const ft = (e) => (t, s = Ce) => {
  (!rs || e === "sp") && Vs(e, (...n) => t(...n), s);
}, ul = ft("bm"), as = ft("m"), fl = ft(
  "bu"
), dl = ft("u"), Mi = ft(
  "bum"
), Pi = ft("um"), hl = ft(
  "sp"
), pl = ft("rtg"), gl = ft("rtc");
function _l(e, t = Ce) {
  Vs("ec", e, t);
}
const ml = /* @__PURE__ */ Symbol.for("v-ndc");
function ue(e, t, s, n) {
  let r;
  const i = s, o = W(e);
  if (o || fe(e)) {
    const l = o && /* @__PURE__ */ $t(e);
    let c = !1, u = !1;
    l && (c = !/* @__PURE__ */ He(e), u = /* @__PURE__ */ ut(e), e = Ds(e)), r = new Array(e.length);
    for (let a = 0, d = e.length; a < d; a++)
      r[a] = t(
        c ? u ? Ft(De(e[a])) : De(e[a]) : e[a],
        a,
        void 0,
        i
      );
  } else if (typeof e == "number") {
    r = new Array(e);
    for (let l = 0; l < e; l++)
      r[l] = t(l + 1, l, void 0, i);
  } else if (ee(e))
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
const mn = (e) => e ? zi(e) ? Us(e) : mn(e.parent) : null, Yt = (
  // Move PURE marker to new line to workaround compiler discarding it
  // due to type annotation
  /* @__PURE__ */ ge(/* @__PURE__ */ Object.create(null), {
    $: (e) => e,
    $el: (e) => e.vnode.el,
    $data: (e) => e.data,
    $props: (e) => e.props,
    $attrs: (e) => e.attrs,
    $slots: (e) => e.slots,
    $refs: (e) => e.refs,
    $parent: (e) => mn(e.parent),
    $root: (e) => mn(e.root),
    $host: (e) => e.ce,
    $emit: (e) => e.emit,
    $options: (e) => Ai(e),
    $forceUpdate: (e) => e.f || (e.f = () => {
      jn(e.update);
    }),
    $nextTick: (e) => e.n || (e.n = Zo.bind(e.proxy)),
    $watch: (e) => sl.bind(e)
  })
), Qs = (e, t) => e !== ne && !e.__isScriptSetup && X(e, t), vl = {
  get({ _: e }, t) {
    if (t === "__v_skip")
      return !0;
    const { ctx: s, setupState: n, data: r, props: i, accessCache: o, type: l, appContext: c } = e;
    if (t[0] !== "$") {
      const _ = o[t];
      if (_ !== void 0)
        switch (_) {
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
        if (Qs(n, t))
          return o[t] = 1, n[t];
        if (r !== ne && X(r, t))
          return o[t] = 2, r[t];
        if (X(i, t))
          return o[t] = 3, i[t];
        if (s !== ne && X(s, t))
          return o[t] = 4, s[t];
        vn && (o[t] = 0);
      }
    }
    const u = Yt[t];
    let a, d;
    if (u)
      return t === "$attrs" && ve(e.attrs, "get", ""), u(e);
    if (
      // css module (injected by vue-loader)
      (a = l.__cssModules) && (a = a[t])
    )
      return a;
    if (s !== ne && X(s, t))
      return o[t] = 4, s[t];
    if (
      // global properties
      d = c.config.globalProperties, X(d, t)
    )
      return d[t];
  },
  set({ _: e }, t, s) {
    const { data: n, setupState: r, ctx: i } = e;
    return Qs(r, t) ? (r[t] = s, !0) : n !== ne && X(n, t) ? (n[t] = s, !0) : X(e.props, t) || t[0] === "$" && t.slice(1) in e ? !1 : (i[t] = s, !0);
  },
  has({
    _: { data: e, setupState: t, accessCache: s, ctx: n, appContext: r, props: i, type: o }
  }, l) {
    let c;
    return !!(s[l] || e !== ne && l[0] !== "$" && X(e, l) || Qs(t, l) || X(i, l) || X(n, l) || X(Yt, l) || X(r.config.globalProperties, l) || (c = o.__cssModules) && c[l]);
  },
  defineProperty(e, t, s) {
    return s.get != null ? e._.accessCache[t] = 0 : X(s, "value") && this.set(e, t, s.value, null), Reflect.defineProperty(e, t, s);
  }
};
function cr(e) {
  return W(e) ? e.reduce(
    (t, s) => (t[s] = null, t),
    {}
  ) : e;
}
let vn = !0;
function yl(e) {
  const t = Ai(e), s = e.proxy, n = e.ctx;
  vn = !1, t.beforeCreate && ar(t.beforeCreate, e, "bc");
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
    mounted: _,
    beforeUpdate: k,
    updated: F,
    activated: E,
    deactivated: j,
    beforeDestroy: P,
    beforeUnmount: T,
    destroyed: z,
    unmounted: R,
    render: Z,
    renderTracked: te,
    renderTriggered: ce,
    errorCaptured: H,
    serverPrefetch: $,
    // public API
    expose: S,
    inheritAttrs: B,
    // assets
    components: O,
    directives: le,
    filters: _e
  } = t;
  if (u && xl(u, n, null), o)
    for (const ae in o) {
      const re = o[ae];
      G(re) && (n[ae] = re.bind(s));
    }
  if (r) {
    const ae = r.call(s, s);
    ee(ae) && (e.data = /* @__PURE__ */ Rs(ae));
  }
  if (vn = !0, i)
    for (const ae in i) {
      const re = i[ae], _t = G(re) ? re.bind(s, s) : G(re.get) ? re.get.bind(s, s) : ze, fs = !G(re) && G(re.set) ? re.set.bind(s) : ze, mt = Me({
        get: _t,
        set: fs
      });
      Object.defineProperty(n, ae, {
        enumerable: !0,
        configurable: !0,
        get: () => mt.value,
        set: (je) => mt.value = je
      });
    }
  if (l)
    for (const ae in l)
      Li(l[ae], n, s, ae);
  if (c) {
    const ae = G(c) ? c.call(s) : c;
    Reflect.ownKeys(ae).forEach((re) => {
      Xo(re, ae[re]);
    });
  }
  a && ar(a, e, "c");
  function he(ae, re) {
    W(re) ? re.forEach((_t) => ae(_t.bind(s))) : re && ae(re.bind(s));
  }
  if (he(ul, d), he(as, _), he(fl, k), he(dl, F), he(ll, E), he(cl, j), he(_l, H), he(gl, te), he(pl, ce), he(Mi, T), he(Pi, R), he(hl, $), W(S))
    if (S.length) {
      const ae = e.exposed || (e.exposed = {});
      S.forEach((re) => {
        Object.defineProperty(ae, re, {
          get: () => s[re],
          set: (_t) => s[re] = _t,
          enumerable: !0
        });
      });
    } else e.exposed || (e.exposed = {});
  Z && e.render === ze && (e.render = Z), B != null && (e.inheritAttrs = B), O && (e.components = O), le && (e.directives = le), $ && $i(e);
}
function xl(e, t, s = ze) {
  W(e) && (e = yn(e));
  for (const n in e) {
    const r = e[n];
    let i;
    ee(r) ? "default" in r ? i = vs(
      r.from || n,
      r.default,
      !0
    ) : i = vs(r.from || n) : i = vs(r), /* @__PURE__ */ ye(i) ? Object.defineProperty(t, n, {
      enumerable: !0,
      configurable: !0,
      get: () => i.value,
      set: (o) => i.value = o
    }) : t[n] = i;
  }
}
function ar(e, t, s) {
  Re(
    W(e) ? e.map((n) => n.bind(t.proxy)) : e.bind(t.proxy),
    t,
    s
  );
}
function Li(e, t, s, n) {
  let r = n.includes(".") ? _i(s, n) : () => s[n];
  if (fe(e)) {
    const i = t[e];
    G(i) && Ye(r, i);
  } else if (G(e))
    Ye(r, e.bind(s));
  else if (ee(e))
    if (W(e))
      e.forEach((i) => Li(i, t, s, n));
    else {
      const i = G(e.handler) ? e.handler.bind(s) : t[e.handler];
      G(i) && Ye(r, i, e);
    }
}
function Ai(e) {
  const t = e.type, { mixins: s, extends: n } = t, {
    mixins: r,
    optionsCache: i,
    config: { optionMergeStrategies: o }
  } = e.appContext, l = i.get(t);
  let c;
  return l ? c = l : !r.length && !s && !n ? c = t : (c = {}, r.length && r.forEach(
    (u) => Ss(c, u, o, !0)
  ), Ss(c, t, o)), ee(t) && i.set(t, c), c;
}
function Ss(e, t, s, n = !1) {
  const { mixins: r, extends: i } = t;
  i && Ss(e, i, s, !0), r && r.forEach(
    (o) => Ss(e, o, s, !0)
  );
  for (const o in t)
    if (!(n && o === "expose")) {
      const l = bl[o] || s && s[o];
      e[o] = l ? l(e[o], t[o]) : t[o];
    }
  return e;
}
const bl = {
  data: ur,
  props: fr,
  emits: fr,
  // objects
  methods: Ut,
  computed: Ut,
  // lifecycle
  beforeCreate: xe,
  created: xe,
  beforeMount: xe,
  mounted: xe,
  beforeUpdate: xe,
  updated: xe,
  beforeDestroy: xe,
  beforeUnmount: xe,
  destroyed: xe,
  unmounted: xe,
  activated: xe,
  deactivated: xe,
  errorCaptured: xe,
  serverPrefetch: xe,
  // assets
  components: Ut,
  directives: Ut,
  // watch
  watch: Cl,
  // provide / inject
  provide: ur,
  inject: wl
};
function ur(e, t) {
  return t ? e ? function() {
    return ge(
      G(e) ? e.call(this, this) : e,
      G(t) ? t.call(this, this) : t
    );
  } : t : e;
}
function wl(e, t) {
  return Ut(yn(e), yn(t));
}
function yn(e) {
  if (W(e)) {
    const t = {};
    for (let s = 0; s < e.length; s++)
      t[e[s]] = e[s];
    return t;
  }
  return e;
}
function xe(e, t) {
  return e ? [...new Set([].concat(e, t))] : t;
}
function Ut(e, t) {
  return e ? ge(/* @__PURE__ */ Object.create(null), e, t) : t;
}
function fr(e, t) {
  return e ? W(e) && W(t) ? [.../* @__PURE__ */ new Set([...e, ...t])] : ge(
    /* @__PURE__ */ Object.create(null),
    cr(e),
    cr(t ?? {})
  ) : t;
}
function Cl(e, t) {
  if (!e) return t;
  if (!t) return e;
  const s = ge(/* @__PURE__ */ Object.create(null), e);
  for (const n in t)
    s[n] = xe(e[n], t[n]);
  return s;
}
function Ei() {
  return {
    app: null,
    config: {
      isNativeTag: jr,
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
let kl = 0;
function Tl(e, t) {
  return function(n, r = null) {
    G(n) || (n = ge({}, n)), r != null && !ee(r) && (r = null);
    const i = Ei(), o = /* @__PURE__ */ new WeakSet(), l = [];
    let c = !1;
    const u = i.app = {
      _uid: kl++,
      _component: n,
      _props: r,
      _container: null,
      _context: i,
      _instance: null,
      version: sc,
      get config() {
        return i.config;
      },
      set config(a) {
      },
      use(a, ...d) {
        return o.has(a) || (a && G(a.install) ? (o.add(a), a.install(u, ...d)) : G(a) && (o.add(a), a(u, ...d))), u;
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
      mount(a, d, _) {
        if (!c) {
          const k = u._ceVNode || ke(n, r);
          return k.appContext = i, _ === !0 ? _ = "svg" : _ === !1 && (_ = void 0), e(k, a, _), c = !0, u._container = a, a.__vue_app__ = u, Us(k.component);
        }
      },
      onUnmount(a) {
        l.push(a);
      },
      unmount() {
        c && (Re(
          l,
          u._instance,
          16
        ), e(null, u._container), delete u._container.__vue_app__);
      },
      provide(a, d) {
        return i.provides[a] = d, u;
      },
      runWithContext(a) {
        const d = It;
        It = u;
        try {
          return a();
        } finally {
          It = d;
        }
      }
    };
    return u;
  };
}
let It = null;
const $l = (e, t) => t === "modelValue" || t === "model-value" ? e.modelModifiers : e[`${t}Modifiers`] || e[`${Fe(t)}Modifiers`] || e[`${St(t)}Modifiers`];
function Sl(e, t, ...s) {
  if (e.isUnmounted) return;
  const n = e.vnode.props || ne;
  let r = s;
  const i = t.startsWith("update:"), o = i && $l(n, t.slice(7));
  o && (o.trim && (r = s.map((a) => fe(a) ? a.trim() : a)), o.number && (r = s.map(Ln)));
  let l, c = n[l = qs(t)] || // also try camelCase event handler (#2249)
  n[l = qs(Fe(t))];
  !c && i && (c = n[l = qs(St(t))]), c && Re(
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
    e.emitted[l] = !0, Re(
      u,
      e,
      6,
      r
    );
  }
}
const Ml = /* @__PURE__ */ new WeakMap();
function Ii(e, t, s = !1) {
  const n = s ? Ml : t.emitsCache, r = n.get(e);
  if (r !== void 0)
    return r;
  const i = e.emits;
  let o = {}, l = !1;
  if (!G(e)) {
    const c = (u) => {
      const a = Ii(u, t, !0);
      a && (l = !0, ge(o, a));
    };
    !s && t.mixins.length && t.mixins.forEach(c), e.extends && c(e.extends), e.mixins && e.mixins.forEach(c);
  }
  return !i && !l ? (ee(e) && n.set(e, null), null) : (W(i) ? i.forEach((c) => o[c] = null) : ge(o, i), ee(e) && n.set(e, o), o);
}
function Ws(e, t) {
  return !e || !Is(t) ? !1 : (t = t.slice(2).replace(/Once$/, ""), X(e, t[0].toLowerCase() + t.slice(1)) || X(e, St(t)) || X(e, t));
}
function dr(e) {
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
    data: _,
    setupState: k,
    ctx: F,
    inheritAttrs: E
  } = e, j = Ts(e);
  let P, T;
  try {
    if (s.shapeFlag & 4) {
      const R = r || n, Z = R;
      P = Ge(
        u.call(
          Z,
          R,
          a,
          d,
          k,
          _,
          F
        )
      ), T = l;
    } else {
      const R = t;
      P = Ge(
        R.length > 1 ? R(
          d,
          { attrs: l, slots: o, emit: c }
        ) : R(
          d,
          null
        )
      ), T = t.props ? l : Pl(l);
    }
  } catch (R) {
    Qt.length = 0, js(R, e, 1), P = ke(we);
  }
  let z = P;
  if (T && E !== !1) {
    const R = Object.keys(T), { shapeFlag: Z } = z;
    R.length && Z & 7 && (i && R.some(Sn) && (T = Ll(
      T,
      i
    )), z = gt(z, T, !1, !0));
  }
  return s.dirs && (z = gt(z, null, !1, !0), z.dirs = z.dirs ? z.dirs.concat(s.dirs) : s.dirs), s.transition && ss(z, s.transition), P = z, Ts(j), P;
}
const Pl = (e) => {
  let t;
  for (const s in e)
    (s === "class" || s === "style" || Is(s)) && ((t || (t = {}))[s] = e[s]);
  return t;
}, Ll = (e, t) => {
  const s = {};
  for (const n in e)
    (!Sn(n) || !(n.slice(9) in t)) && (s[n] = e[n]);
  return s;
};
function Al(e, t, s) {
  const { props: n, children: r, component: i } = e, { props: o, children: l, patchFlag: c } = t, u = i.emitsOptions;
  if (t.dirs || t.transition)
    return !0;
  if (s && c >= 0) {
    if (c & 1024)
      return !0;
    if (c & 16)
      return n ? hr(n, o, u) : !!o;
    if (c & 8) {
      const a = t.dynamicProps;
      for (let d = 0; d < a.length; d++) {
        const _ = a[d];
        if (Hi(o, n, _) && !Ws(u, _))
          return !0;
      }
    }
  } else
    return (r || l) && (!l || !l.$stable) ? !0 : n === o ? !1 : n ? o ? hr(n, o, u) : !0 : !!o;
  return !1;
}
function hr(e, t, s) {
  const n = Object.keys(t);
  if (n.length !== Object.keys(e).length)
    return !0;
  for (let r = 0; r < n.length; r++) {
    const i = n[r];
    if (Hi(t, e, i) && !Ws(s, i))
      return !0;
  }
  return !1;
}
function Hi(e, t, s) {
  const n = e[s], r = t[s];
  return s === "style" && ee(n) && ee(r) ? !ls(n, r) : n !== r;
}
function El({ vnode: e, parent: t }, s) {
  for (; t; ) {
    const n = t.subTree;
    if (n.suspense && n.suspense.activeBranch === e && (n.el = e.el), n === e)
      (e = t.vnode).el = s, t = t.parent;
    else
      break;
  }
}
const Fi = {}, Oi = () => Object.create(Fi), Di = (e) => Object.getPrototypeOf(e) === Fi;
function Il(e, t, s, n = !1) {
  const r = {}, i = Oi();
  e.propsDefaults = /* @__PURE__ */ Object.create(null), Ri(e, t, r, i);
  for (const o in e.propsOptions[0])
    o in r || (r[o] = void 0);
  s ? e.props = n ? r : /* @__PURE__ */ No(r) : e.type.props ? e.props = r : e.props = i, e.attrs = i;
}
function Hl(e, t, s, n) {
  const {
    props: r,
    attrs: i,
    vnode: { patchFlag: o }
  } = e, l = /* @__PURE__ */ Q(r), [c] = e.propsOptions;
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
        let _ = a[d];
        if (Ws(e.emitsOptions, _))
          continue;
        const k = t[_];
        if (c)
          if (X(i, _))
            k !== i[_] && (i[_] = k, u = !0);
          else {
            const F = Fe(_);
            r[F] = xn(
              c,
              l,
              F,
              k,
              e,
              !1
            );
          }
        else
          k !== i[_] && (i[_] = k, u = !0);
      }
    }
  } else {
    Ri(e, t, r, i) && (u = !0);
    let a;
    for (const d in l)
      (!t || // for camelCase
      !X(t, d) && // it's possible the original props was passed in as kebab-case
      // and converted to camelCase (#955)
      ((a = St(d)) === d || !X(t, a))) && (c ? s && // for camelCase
      (s[d] !== void 0 || // for kebab-case
      s[a] !== void 0) && (r[d] = xn(
        c,
        l,
        d,
        void 0,
        e,
        !0
      )) : delete r[d]);
    if (i !== l)
      for (const d in i)
        (!t || !X(t, d)) && (delete i[d], u = !0);
  }
  u && ot(e.attrs, "set", "");
}
function Ri(e, t, s, n) {
  const [r, i] = e.propsOptions;
  let o = !1, l;
  if (t)
    for (let c in t) {
      if (Kt(c))
        continue;
      const u = t[c];
      let a;
      r && X(r, a = Fe(c)) ? !i || !i.includes(a) ? s[a] = u : (l || (l = {}))[a] = u : Ws(e.emitsOptions, c) || (!(c in n) || u !== n[c]) && (n[c] = u, o = !0);
    }
  if (i) {
    const c = /* @__PURE__ */ Q(s), u = l || ne;
    for (let a = 0; a < i.length; a++) {
      const d = i[a];
      s[d] = xn(
        r,
        c,
        d,
        u[d],
        e,
        !X(u, d)
      );
    }
  }
  return o;
}
function xn(e, t, s, n, r, i) {
  const o = e[s];
  if (o != null) {
    const l = X(o, "default");
    if (l && n === void 0) {
      const c = o.default;
      if (o.type !== Function && !o.skipFactory && G(c)) {
        const { propsDefaults: u } = r;
        if (s in u)
          n = u[s];
        else {
          const a = us(r);
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
    ] && (n === "" || n === St(s)) && (n = !0));
  }
  return n;
}
const Fl = /* @__PURE__ */ new WeakMap();
function ji(e, t, s = !1) {
  const n = s ? Fl : t.propsCache, r = n.get(e);
  if (r)
    return r;
  const i = e.props, o = {}, l = [];
  let c = !1;
  if (!G(e)) {
    const a = (d) => {
      c = !0;
      const [_, k] = ji(d, t, !0);
      ge(o, _), k && l.push(...k);
    };
    !s && t.mixins.length && t.mixins.forEach(a), e.extends && a(e.extends), e.mixins && e.mixins.forEach(a);
  }
  if (!i && !c)
    return ee(e) && n.set(e, Lt), Lt;
  if (W(i))
    for (let a = 0; a < i.length; a++) {
      const d = Fe(i[a]);
      pr(d) && (o[d] = ne);
    }
  else if (i)
    for (const a in i) {
      const d = Fe(a);
      if (pr(d)) {
        const _ = i[a], k = o[d] = W(_) || G(_) ? { type: _ } : ge({}, _), F = k.type;
        let E = !1, j = !0;
        if (W(F))
          for (let P = 0; P < F.length; ++P) {
            const T = F[P], z = G(T) && T.name;
            if (z === "Boolean") {
              E = !0;
              break;
            } else z === "String" && (j = !1);
          }
        else
          E = G(F) && F.name === "Boolean";
        k[
          0
          /* shouldCast */
        ] = E, k[
          1
          /* shouldCastTrue */
        ] = j, (E || X(k, "default")) && l.push(d);
      }
    }
  const u = [o, l];
  return ee(e) && n.set(e, u), u;
}
function pr(e) {
  return e[0] !== "$" && !Kt(e);
}
const Vn = (e) => e === "_" || e === "_ctx" || e === "$stable", Wn = (e) => W(e) ? e.map(Ge) : [Ge(e)], Ol = (e, t, s) => {
  if (t._n)
    return t;
  const n = Nn((...r) => Wn(t(...r)), s);
  return n._c = !1, n;
}, Ni = (e, t, s) => {
  const n = e._ctx;
  for (const r in e) {
    if (Vn(r)) continue;
    const i = e[r];
    if (G(i))
      t[r] = Ol(r, i, n);
    else if (i != null) {
      const o = Wn(i);
      t[r] = () => o;
    }
  }
}, Vi = (e, t) => {
  const s = Wn(t);
  e.slots.default = () => s;
}, Wi = (e, t, s) => {
  for (const n in t)
    (s || !Vn(n)) && (e[n] = t[n]);
}, Dl = (e, t, s) => {
  const n = e.slots = Oi();
  if (e.vnode.shapeFlag & 32) {
    const r = t._;
    r ? (Wi(n, t, s), s && Ur(n, "_", r, !0)) : Ni(t, n);
  } else t && Vi(e, t);
}, Rl = (e, t, s) => {
  const { vnode: n, slots: r } = e;
  let i = !0, o = ne;
  if (n.shapeFlag & 32) {
    const l = t._;
    l ? s && l === 1 ? i = !1 : Wi(r, t, s) : (i = !t.$stable, Ni(t, r)), o = t;
  } else t && (Vi(e, t), o = { default: 1 });
  if (i)
    for (const l in r)
      !Vn(l) && o[l] == null && delete r[l];
}, me = Bl;
function jl(e) {
  return Nl(e);
}
function Nl(e, t) {
  const s = Os();
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
    nextSibling: _,
    setScopeId: k = ze,
    insertStaticContent: F
  } = e, E = (f, h, y, C = null, x = null, b = null, A = void 0, L = null, M = !!h.dynamicChildren) => {
    if (f === h)
      return;
    f && !Ct(f, h) && (C = ds(f), je(f, x, b, !0), f = null), h.patchFlag === -2 && (M = !1, h.dynamicChildren = null);
    const { type: w, ref: U, shapeFlag: I } = h;
    switch (w) {
      case Bs:
        j(f, h, y, C);
        break;
      case we:
        P(f, h, y, C);
        break;
      case en:
        f == null && T(h, y, C, A);
        break;
      case J:
        O(
          f,
          h,
          y,
          C,
          x,
          b,
          A,
          L,
          M
        );
        break;
      default:
        I & 1 ? Z(
          f,
          h,
          y,
          C,
          x,
          b,
          A,
          L,
          M
        ) : I & 6 ? le(
          f,
          h,
          y,
          C,
          x,
          b,
          A,
          L,
          M
        ) : (I & 64 || I & 128) && w.process(
          f,
          h,
          y,
          C,
          x,
          b,
          A,
          L,
          M,
          Dt
        );
    }
    U != null && x ? zt(U, f && f.ref, b, h || f, !h) : U == null && f && f.ref != null && zt(f.ref, null, b, f, !0);
  }, j = (f, h, y, C) => {
    if (f == null)
      n(
        h.el = l(h.children),
        y,
        C
      );
    else {
      const x = h.el = f.el;
      h.children !== f.children && u(x, h.children);
    }
  }, P = (f, h, y, C) => {
    f == null ? n(
      h.el = c(h.children || ""),
      y,
      C
    ) : h.el = f.el;
  }, T = (f, h, y, C) => {
    [f.el, f.anchor] = F(
      f.children,
      h,
      y,
      C,
      f.el,
      f.anchor
    );
  }, z = ({ el: f, anchor: h }, y, C) => {
    let x;
    for (; f && f !== h; )
      x = _(f), n(f, y, C), f = x;
    n(h, y, C);
  }, R = ({ el: f, anchor: h }) => {
    let y;
    for (; f && f !== h; )
      y = _(f), r(f), f = y;
    r(h);
  }, Z = (f, h, y, C, x, b, A, L, M) => {
    if (h.type === "svg" ? A = "svg" : h.type === "math" && (A = "mathml"), f == null)
      te(
        h,
        y,
        C,
        x,
        b,
        A,
        L,
        M
      );
    else {
      const w = f.el && f.el._isVueCE ? f.el : null;
      try {
        w && w._beginPatch(), $(
          f,
          h,
          x,
          b,
          A,
          L,
          M
        );
      } finally {
        w && w._endPatch();
      }
    }
  }, te = (f, h, y, C, x, b, A, L) => {
    let M, w;
    const { props: U, shapeFlag: I, transition: N, dirs: q } = f;
    if (M = f.el = o(
      f.type,
      b,
      U && U.is,
      U
    ), I & 8 ? a(M, f.children) : I & 16 && H(
      f.children,
      M,
      null,
      C,
      x,
      Xs(f, b),
      A,
      L
    ), q && vt(f, null, C, "created"), ce(M, f, f.scopeId, A, C), U) {
      for (const ie in U)
        ie !== "value" && !Kt(ie) && i(M, ie, null, U[ie], b, C);
      "value" in U && i(M, "value", null, U.value, b), (w = U.onVnodeBeforeMount) && Be(w, C, f);
    }
    q && vt(f, null, C, "beforeMount");
    const Y = Vl(x, N);
    Y && N.beforeEnter(M), n(M, h, y), ((w = U && U.onVnodeMounted) || Y || q) && me(() => {
      w && Be(w, C, f), Y && N.enter(M), q && vt(f, null, C, "mounted");
    }, x);
  }, ce = (f, h, y, C, x) => {
    if (y && k(f, y), C)
      for (let b = 0; b < C.length; b++)
        k(f, C[b]);
    if (x) {
      let b = x.subTree;
      if (h === b || Ki(b.type) && (b.ssContent === h || b.ssFallback === h)) {
        const A = x.vnode;
        ce(
          f,
          A,
          A.scopeId,
          A.slotScopeIds,
          x.parent
        );
      }
    }
  }, H = (f, h, y, C, x, b, A, L, M = 0) => {
    for (let w = M; w < f.length; w++) {
      const U = f[w] = L ? it(f[w]) : Ge(f[w]);
      E(
        null,
        U,
        h,
        y,
        C,
        x,
        b,
        A,
        L
      );
    }
  }, $ = (f, h, y, C, x, b, A) => {
    const L = h.el = f.el;
    let { patchFlag: M, dynamicChildren: w, dirs: U } = h;
    M |= f.patchFlag & 16;
    const I = f.props || ne, N = h.props || ne;
    let q;
    if (y && yt(y, !1), (q = N.onVnodeBeforeUpdate) && Be(q, y, h, f), U && vt(h, f, y, "beforeUpdate"), y && yt(y, !0), (I.innerHTML && N.innerHTML == null || I.textContent && N.textContent == null) && a(L, ""), w ? S(
      f.dynamicChildren,
      w,
      L,
      y,
      C,
      Xs(h, x),
      b
    ) : A || re(
      f,
      h,
      L,
      null,
      y,
      C,
      Xs(h, x),
      b,
      !1
    ), M > 0) {
      if (M & 16)
        B(L, I, N, y, x);
      else if (M & 2 && I.class !== N.class && i(L, "class", null, N.class, x), M & 4 && i(L, "style", I.style, N.style, x), M & 8) {
        const Y = h.dynamicProps;
        for (let ie = 0; ie < Y.length; ie++) {
          const se = Y[ie], Te = I[se], $e = N[se];
          ($e !== Te || se === "value") && i(L, se, Te, $e, x, y);
        }
      }
      M & 1 && f.children !== h.children && a(L, h.children);
    } else !A && w == null && B(L, I, N, y, x);
    ((q = N.onVnodeUpdated) || U) && me(() => {
      q && Be(q, y, h, f), U && vt(h, f, y, "updated");
    }, C);
  }, S = (f, h, y, C, x, b, A) => {
    for (let L = 0; L < h.length; L++) {
      const M = f[L], w = h[L], U = (
        // oldVNode may be an errored async setup() component inside Suspense
        // which will not have a mounted element
        M.el && // - In the case of a Fragment, we need to provide the actual parent
        // of the Fragment itself so it can move its children.
        (M.type === J || // - In the case of different nodes, there is going to be a replacement
        // which also requires the correct parent container
        !Ct(M, w) || // - In the case of a component, it could contain anything.
        M.shapeFlag & 198) ? d(M.el) : (
          // In other cases, the parent container is not actually used so we
          // just pass the block element here to avoid a DOM parentNode call.
          y
        )
      );
      E(
        M,
        w,
        U,
        null,
        C,
        x,
        b,
        A,
        !0
      );
    }
  }, B = (f, h, y, C, x) => {
    if (h !== y) {
      if (h !== ne)
        for (const b in h)
          !Kt(b) && !(b in y) && i(
            f,
            b,
            h[b],
            null,
            x,
            C
          );
      for (const b in y) {
        if (Kt(b)) continue;
        const A = y[b], L = h[b];
        A !== L && b !== "value" && i(f, b, L, A, x, C);
      }
      "value" in y && i(f, "value", h.value, y.value, x);
    }
  }, O = (f, h, y, C, x, b, A, L, M) => {
    const w = h.el = f ? f.el : l(""), U = h.anchor = f ? f.anchor : l("");
    let { patchFlag: I, dynamicChildren: N, slotScopeIds: q } = h;
    q && (L = L ? L.concat(q) : q), f == null ? (n(w, y, C), n(U, y, C), H(
      // #10007
      // such fragment like `<></>` will be compiled into
      // a fragment which doesn't have a children.
      // In this case fallback to an empty array
      h.children || [],
      y,
      U,
      x,
      b,
      A,
      L,
      M
    )) : I > 0 && I & 64 && N && // #2715 the previous fragment could've been a BAILed one as a result
    // of renderSlot() with no valid children
    f.dynamicChildren && f.dynamicChildren.length === N.length ? (S(
      f.dynamicChildren,
      N,
      y,
      x,
      b,
      A,
      L
    ), // #2080 if the stable fragment has a key, it's a <template v-for> that may
    //  get moved around. Make sure all root level vnodes inherit el.
    // #2134 or if it's a component root, it may also get moved around
    // as the component is being moved.
    (h.key != null || x && h === x.subTree) && Bn(
      f,
      h,
      !0
      /* shallow */
    )) : re(
      f,
      h,
      y,
      U,
      x,
      b,
      A,
      L,
      M
    );
  }, le = (f, h, y, C, x, b, A, L, M) => {
    h.slotScopeIds = L, f == null ? h.shapeFlag & 512 ? x.ctx.activate(
      h,
      y,
      C,
      A,
      M
    ) : _e(
      h,
      y,
      C,
      x,
      b,
      A,
      M
    ) : Xe(f, h, M);
  }, _e = (f, h, y, C, x, b, A) => {
    const L = f.component = Zl(
      f,
      C,
      x
    );
    if (Ns(f) && (L.ctx.renderer = Dt), Yl(L, !1, A), L.asyncDep) {
      if (x && x.registerDep(L, he, A), !f.el) {
        const M = L.subTree = ke(we);
        P(null, M, h, y), f.placeholder = M.el;
      }
    } else
      he(
        L,
        f,
        h,
        y,
        x,
        b,
        A
      );
  }, Xe = (f, h, y) => {
    const C = h.component = f.component;
    if (Al(f, h, y))
      if (C.asyncDep && !C.asyncResolved) {
        ae(C, h, y);
        return;
      } else
        C.next = h, C.update();
    else
      h.el = f.el, C.vnode = h;
  }, he = (f, h, y, C, x, b, A) => {
    const L = () => {
      if (f.isMounted) {
        let { next: I, bu: N, u: q, parent: Y, vnode: ie } = f;
        {
          const Ve = Bi(f);
          if (Ve) {
            I && (I.el = ie.el, ae(f, I, A)), Ve.asyncDep.then(() => {
              me(() => {
                f.isUnmounted || w();
              }, x);
            });
            return;
          }
        }
        let se = I, Te;
        yt(f, !1), I ? (I.el = ie.el, ae(f, I, A)) : I = ie, N && ms(N), (Te = I.props && I.props.onVnodeBeforeUpdate) && Be(Te, Y, I, ie), yt(f, !0);
        const $e = dr(f), Ne = f.subTree;
        f.subTree = $e, E(
          Ne,
          $e,
          // parent may have changed if it's in a teleport
          d(Ne.el),
          // anchor may have changed if it's in a fragment
          ds(Ne),
          f,
          x,
          b
        ), I.el = $e.el, se === null && El(f, $e.el), q && me(q, x), (Te = I.props && I.props.onVnodeUpdated) && me(
          () => Be(Te, Y, I, ie),
          x
        );
      } else {
        let I;
        const { el: N, props: q } = h, { bm: Y, m: ie, parent: se, root: Te, type: $e } = f, Ne = Zt(h);
        yt(f, !1), Y && ms(Y), !Ne && (I = q && q.onVnodeBeforeMount) && Be(I, se, h), yt(f, !0);
        {
          Te.ce && Te.ce._hasShadowRoot() && Te.ce._injectChildStyle(
            $e,
            f.parent ? f.parent.type : void 0
          );
          const Ve = f.subTree = dr(f);
          E(
            null,
            Ve,
            y,
            C,
            f,
            x,
            b
          ), h.el = Ve.el;
        }
        if (ie && me(ie, x), !Ne && (I = q && q.onVnodeMounted)) {
          const Ve = h;
          me(
            () => Be(I, se, Ve),
            x
          );
        }
        (h.shapeFlag & 256 || se && Zt(se.vnode) && se.vnode.shapeFlag & 256) && f.a && me(f.a, x), f.isMounted = !0, h = y = C = null;
      }
    };
    f.scope.on();
    const M = f.effect = new zr(L);
    f.scope.off();
    const w = f.update = M.run.bind(M), U = f.job = M.runIfDirty.bind(M);
    U.i = f, U.id = f.uid, M.scheduler = () => jn(U), yt(f, !0), w();
  }, ae = (f, h, y) => {
    h.component = f;
    const C = f.vnode.props;
    f.vnode = h, f.next = null, Hl(f, h.props, C, y), Rl(f, h.children, y), ct(), tr(f), at();
  }, re = (f, h, y, C, x, b, A, L, M = !1) => {
    const w = f && f.children, U = f ? f.shapeFlag : 0, I = h.children, { patchFlag: N, shapeFlag: q } = h;
    if (N > 0) {
      if (N & 128) {
        fs(
          w,
          I,
          y,
          C,
          x,
          b,
          A,
          L,
          M
        );
        return;
      } else if (N & 256) {
        _t(
          w,
          I,
          y,
          C,
          x,
          b,
          A,
          L,
          M
        );
        return;
      }
    }
    q & 8 ? (U & 16 && Ot(w, x, b), I !== w && a(y, I)) : U & 16 ? q & 16 ? fs(
      w,
      I,
      y,
      C,
      x,
      b,
      A,
      L,
      M
    ) : Ot(w, x, b, !0) : (U & 8 && a(y, ""), q & 16 && H(
      I,
      y,
      C,
      x,
      b,
      A,
      L,
      M
    ));
  }, _t = (f, h, y, C, x, b, A, L, M) => {
    f = f || Lt, h = h || Lt;
    const w = f.length, U = h.length, I = Math.min(w, U);
    let N;
    for (N = 0; N < I; N++) {
      const q = h[N] = M ? it(h[N]) : Ge(h[N]);
      E(
        f[N],
        q,
        y,
        null,
        x,
        b,
        A,
        L,
        M
      );
    }
    w > U ? Ot(
      f,
      x,
      b,
      !0,
      !1,
      I
    ) : H(
      h,
      y,
      C,
      x,
      b,
      A,
      L,
      M,
      I
    );
  }, fs = (f, h, y, C, x, b, A, L, M) => {
    let w = 0;
    const U = h.length;
    let I = f.length - 1, N = U - 1;
    for (; w <= I && w <= N; ) {
      const q = f[w], Y = h[w] = M ? it(h[w]) : Ge(h[w]);
      if (Ct(q, Y))
        E(
          q,
          Y,
          y,
          null,
          x,
          b,
          A,
          L,
          M
        );
      else
        break;
      w++;
    }
    for (; w <= I && w <= N; ) {
      const q = f[I], Y = h[N] = M ? it(h[N]) : Ge(h[N]);
      if (Ct(q, Y))
        E(
          q,
          Y,
          y,
          null,
          x,
          b,
          A,
          L,
          M
        );
      else
        break;
      I--, N--;
    }
    if (w > I) {
      if (w <= N) {
        const q = N + 1, Y = q < U ? h[q].el : C;
        for (; w <= N; )
          E(
            null,
            h[w] = M ? it(h[w]) : Ge(h[w]),
            y,
            Y,
            x,
            b,
            A,
            L,
            M
          ), w++;
      }
    } else if (w > N)
      for (; w <= I; )
        je(f[w], x, b, !0), w++;
    else {
      const q = w, Y = w, ie = /* @__PURE__ */ new Map();
      for (w = Y; w <= N; w++) {
        const Le = h[w] = M ? it(h[w]) : Ge(h[w]);
        Le.key != null && ie.set(Le.key, w);
      }
      let se, Te = 0;
      const $e = N - Y + 1;
      let Ne = !1, Ve = 0;
      const Rt = new Array($e);
      for (w = 0; w < $e; w++) Rt[w] = 0;
      for (w = q; w <= I; w++) {
        const Le = f[w];
        if (Te >= $e) {
          je(Le, x, b, !0);
          continue;
        }
        let We;
        if (Le.key != null)
          We = ie.get(Le.key);
        else
          for (se = Y; se <= N; se++)
            if (Rt[se - Y] === 0 && Ct(Le, h[se])) {
              We = se;
              break;
            }
        We === void 0 ? je(Le, x, b, !0) : (Rt[We - Y] = w + 1, We >= Ve ? Ve = We : Ne = !0, E(
          Le,
          h[We],
          y,
          null,
          x,
          b,
          A,
          L,
          M
        ), Te++);
      }
      const Jn = Ne ? Wl(Rt) : Lt;
      for (se = Jn.length - 1, w = $e - 1; w >= 0; w--) {
        const Le = Y + w, We = h[Le], zn = h[Le + 1], Zn = Le + 1 < U ? (
          // #13559, #14173 fallback to el placeholder for unresolved async component
          zn.el || Ui(zn)
        ) : C;
        Rt[w] === 0 ? E(
          null,
          We,
          y,
          Zn,
          x,
          b,
          A,
          L,
          M
        ) : Ne && (se < 0 || w !== Jn[se] ? mt(We, y, Zn, 2) : se--);
      }
    }
  }, mt = (f, h, y, C, x = null) => {
    const { el: b, type: A, transition: L, children: M, shapeFlag: w } = f;
    if (w & 6) {
      mt(f.component.subTree, h, y, C);
      return;
    }
    if (w & 128) {
      f.suspense.move(h, y, C);
      return;
    }
    if (w & 64) {
      A.move(f, h, y, Dt);
      return;
    }
    if (A === J) {
      n(b, h, y);
      for (let I = 0; I < M.length; I++)
        mt(M[I], h, y, C);
      n(f.anchor, h, y);
      return;
    }
    if (A === en) {
      z(f, h, y);
      return;
    }
    if (C !== 2 && w & 1 && L)
      if (C === 0)
        L.beforeEnter(b), n(b, h, y), me(() => L.enter(b), x);
      else {
        const { leave: I, delayLeave: N, afterLeave: q } = L, Y = () => {
          f.ctx.isUnmounted ? r(b) : n(b, h, y);
        }, ie = () => {
          b._isLeaving && b[Ke](
            !0
            /* cancelled */
          ), I(b, () => {
            Y(), q && q();
          });
        };
        N ? N(b, Y, ie) : ie();
      }
    else
      n(b, h, y);
  }, je = (f, h, y, C = !1, x = !1) => {
    const {
      type: b,
      props: A,
      ref: L,
      children: M,
      dynamicChildren: w,
      shapeFlag: U,
      patchFlag: I,
      dirs: N,
      cacheIndex: q
    } = f;
    if (I === -2 && (x = !1), L != null && (ct(), zt(L, null, y, f, !0), at()), q != null && (h.renderCache[q] = void 0), U & 256) {
      h.ctx.deactivate(f);
      return;
    }
    const Y = U & 1 && N, ie = !Zt(f);
    let se;
    if (ie && (se = A && A.onVnodeBeforeUnmount) && Be(se, h, f), U & 6)
      oo(f.component, y, C);
    else {
      if (U & 128) {
        f.suspense.unmount(y, C);
        return;
      }
      Y && vt(f, null, h, "beforeUnmount"), U & 64 ? f.type.remove(
        f,
        h,
        y,
        Dt,
        C
      ) : w && // #5154
      // when v-once is used inside a block, setBlockTracking(-1) marks the
      // parent block with hasOnce: true
      // so that it doesn't take the fast path during unmount - otherwise
      // components nested in v-once are never unmounted.
      !w.hasOnce && // #1153: fast path should not be taken for non-stable (v-for) fragments
      (b !== J || I > 0 && I & 64) ? Ot(
        w,
        h,
        y,
        !1,
        !0
      ) : (b === J && I & 384 || !x && U & 16) && Ot(M, h, y), C && qn(f);
    }
    (ie && (se = A && A.onVnodeUnmounted) || Y) && me(() => {
      se && Be(se, h, f), Y && vt(f, null, h, "unmounted");
    }, y);
  }, qn = (f) => {
    const { type: h, el: y, anchor: C, transition: x } = f;
    if (h === J) {
      io(y, C);
      return;
    }
    if (h === en) {
      R(f);
      return;
    }
    const b = () => {
      r(y), x && !x.persisted && x.afterLeave && x.afterLeave();
    };
    if (f.shapeFlag & 1 && x && !x.persisted) {
      const { leave: A, delayLeave: L } = x, M = () => A(y, b);
      L ? L(f.el, b, M) : M();
    } else
      b();
  }, io = (f, h) => {
    let y;
    for (; f !== h; )
      y = _(f), r(f), f = y;
    r(h);
  }, oo = (f, h, y) => {
    const { bum: C, scope: x, job: b, subTree: A, um: L, m: M, a: w } = f;
    gr(M), gr(w), C && ms(C), x.stop(), b && (b.flags |= 8, je(A, f, h, y)), L && me(L, h), me(() => {
      f.isUnmounted = !0;
    }, h);
  }, Ot = (f, h, y, C = !1, x = !1, b = 0) => {
    for (let A = b; A < f.length; A++)
      je(f[A], h, y, C, x);
  }, ds = (f) => {
    if (f.shapeFlag & 6)
      return ds(f.component.subTree);
    if (f.shapeFlag & 128)
      return f.suspense.next();
    const h = _(f.anchor || f.el), y = h && h[mi];
    return y ? _(y) : h;
  };
  let Ks = !1;
  const Gn = (f, h, y) => {
    let C;
    f == null ? h._vnode && (je(h._vnode, null, null, !0), C = h._vnode.component) : E(
      h._vnode || null,
      f,
      h,
      null,
      null,
      null,
      y
    ), h._vnode = f, Ks || (Ks = !0, tr(C), di(), Ks = !1);
  }, Dt = {
    p: E,
    um: je,
    m: mt,
    r: qn,
    mt: _e,
    mc: H,
    pc: re,
    pbc: S,
    n: ds,
    o: e
  };
  return {
    render: Gn,
    hydrate: void 0,
    createApp: Tl(Gn)
  };
}
function Xs({ type: e, props: t }, s) {
  return s === "svg" && e === "foreignObject" || s === "mathml" && e === "annotation-xml" && t && t.encoding && t.encoding.includes("html") ? void 0 : s;
}
function yt({ effect: e, job: t }, s) {
  s ? (e.flags |= 32, t.flags |= 4) : (e.flags &= -33, t.flags &= -5);
}
function Vl(e, t) {
  return (!e || e && !e.pendingBranch) && t && !t.persisted;
}
function Bn(e, t, s = !1) {
  const n = e.children, r = t.children;
  if (W(n) && W(r))
    for (let i = 0; i < n.length; i++) {
      const o = n[i];
      let l = r[i];
      l.shapeFlag & 1 && !l.dynamicChildren && ((l.patchFlag <= 0 || l.patchFlag === 32) && (l = r[i] = it(r[i]), l.el = o.el), !s && l.patchFlag !== -2 && Bn(o, l)), l.type === Bs && (l.patchFlag === -1 && (l = r[i] = it(l)), l.el = o.el), l.type === we && !l.el && (l.el = o.el);
    }
}
function Wl(e) {
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
function Bi(e) {
  const t = e.subTree.component;
  if (t)
    return t.asyncDep && !t.asyncResolved ? t : Bi(t);
}
function gr(e) {
  if (e)
    for (let t = 0; t < e.length; t++)
      e[t].flags |= 8;
}
function Ui(e) {
  if (e.placeholder)
    return e.placeholder;
  const t = e.component;
  return t ? Ui(t.subTree) : null;
}
const Ki = (e) => e.__isSuspense;
function Bl(e, t) {
  t && t.pendingBranch ? W(e) ? t.effects.push(...e) : t.effects.push(e) : Qo(e);
}
const J = /* @__PURE__ */ Symbol.for("v-fgt"), Bs = /* @__PURE__ */ Symbol.for("v-txt"), we = /* @__PURE__ */ Symbol.for("v-cmt"), en = /* @__PURE__ */ Symbol.for("v-stc"), Qt = [];
let Ae = null;
function m(e = !1) {
  Qt.push(Ae = e ? null : []);
}
function Ul() {
  Qt.pop(), Ae = Qt[Qt.length - 1] || null;
}
let ns = 1;
function Ms(e, t = !1) {
  ns += e, e < 0 && Ae && t && (Ae.hasOnce = !0);
}
function qi(e) {
  return e.dynamicChildren = ns > 0 ? Ae || Lt : null, Ul(), ns > 0 && Ae && Ae.push(e), e;
}
function v(e, t, s, n, r, i) {
  return qi(
    g(
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
function Un(e, t, s, n, r) {
  return qi(
    ke(
      e,
      t,
      s,
      n,
      r,
      !0
    )
  );
}
function Ps(e) {
  return e ? e.__v_isVNode === !0 : !1;
}
function Ct(e, t) {
  return e.type === t.type && e.key === t.key;
}
const Gi = ({ key: e }) => e ?? null, xs = ({
  ref: e,
  ref_key: t,
  ref_for: s
}) => (typeof e == "number" && (e = "" + e), e != null ? fe(e) || /* @__PURE__ */ ye(e) || G(e) ? { i: Ie, r: e, k: t, f: !!s } : e : null);
function g(e, t = null, s = null, n = 0, r = null, i = e === J ? 0 : 1, o = !1, l = !1) {
  const c = {
    __v_isVNode: !0,
    __v_skip: !0,
    type: e,
    props: t,
    key: t && Gi(t),
    ref: t && xs(t),
    scopeId: pi,
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
    ctx: Ie
  };
  return l ? (Kn(c, s), i & 128 && e.normalize(c)) : s && (c.shapeFlag |= fe(s) ? 8 : 16), ns > 0 && // avoid a block node from tracking itself
  !o && // has current parent block
  Ae && // presence of a patch flag indicates this node needs patching on updates.
  // component nodes also should always be patched, because even if the
  // component doesn't need to update, it needs to persist the instance on to
  // the next vnode so that it can be properly unmounted later.
  (c.patchFlag > 0 || i & 6) && // the EVENTS flag is only for hydration and if it is the only flag, the
  // vnode should not be considered dynamic due to handler caching.
  c.patchFlag !== 32 && Ae.push(c), c;
}
const ke = Kl;
function Kl(e, t = null, s = null, n = 0, r = null, i = !1) {
  if ((!e || e === ml) && (e = we), Ps(e)) {
    const l = gt(
      e,
      t,
      !0
      /* mergeRef: true */
    );
    return s && Kn(l, s), ns > 0 && !i && Ae && (l.shapeFlag & 6 ? Ae[Ae.indexOf(e)] = l : Ae.push(l)), l.patchFlag = -2, l;
  }
  if (tc(e) && (e = e.__vccOpts), t) {
    t = ql(t);
    let { class: l, style: c } = t;
    l && !fe(l) && (t.class = pe(l)), ee(c) && (/* @__PURE__ */ Rn(c) && !W(c) && (c = ge({}, c)), t.style = An(c));
  }
  const o = fe(e) ? 1 : Ki(e) ? 128 : vi(e) ? 64 : ee(e) ? 4 : G(e) ? 2 : 0;
  return g(
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
function ql(e) {
  return e ? /* @__PURE__ */ Rn(e) || Di(e) ? ge({}, e) : e : null;
}
function gt(e, t, s = !1, n = !1) {
  const { props: r, ref: i, patchFlag: o, children: l, transition: c } = e, u = t ? Gl(r || {}, t) : r, a = {
    __v_isVNode: !0,
    __v_skip: !0,
    type: e.type,
    props: u,
    key: u && Gi(u),
    ref: t && t.ref ? (
      // #2078 in the case of <component :is="vnode" ref="extra"/>
      // if the vnode itself already has a ref, cloneVNode will need to merge
      // the refs so the single vnode can be set on multiple refs
      s && i ? W(i) ? i.concat(xs(t)) : [i, xs(t)] : xs(t)
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
    patchFlag: t && e.type !== J ? o === -1 ? 16 : o | 16 : o,
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
    ssContent: e.ssContent && gt(e.ssContent),
    ssFallback: e.ssFallback && gt(e.ssFallback),
    placeholder: e.placeholder,
    el: e.el,
    anchor: e.anchor,
    ctx: e.ctx,
    ce: e.ce
  };
  return c && n && ss(
    a,
    c.clone(a)
  ), a;
}
function de(e = " ", t = 0) {
  return ke(Bs, null, e, t);
}
function V(e = "", t = !1) {
  return t ? (m(), Un(we, null, e)) : ke(we, null, e);
}
function Ge(e) {
  return e == null || typeof e == "boolean" ? ke(we) : W(e) ? ke(
    J,
    null,
    // #3666, avoid reference pollution when reusing vnode
    e.slice()
  ) : Ps(e) ? it(e) : ke(Bs, null, String(e));
}
function it(e) {
  return e.el === null && e.patchFlag !== -1 || e.memo ? e : gt(e);
}
function Kn(e, t) {
  let s = 0;
  const { shapeFlag: n } = e;
  if (t == null)
    t = null;
  else if (W(t))
    s = 16;
  else if (typeof t == "object")
    if (n & 65) {
      const r = t.default;
      r && (r._c && (r._d = !1), Kn(e, r()), r._c && (r._d = !0));
      return;
    } else {
      s = 32;
      const r = t._;
      !r && !Di(t) ? t._ctx = Ie : r === 3 && Ie && (Ie.slots._ === 1 ? t._ = 1 : (t._ = 2, e.patchFlag |= 1024));
    }
  else G(t) ? (t = { default: t, _ctx: Ie }, s = 32) : (t = String(t), n & 64 ? (s = 16, t = [de(t)]) : s = 8);
  e.children = t, e.shapeFlag |= s;
}
function Gl(...e) {
  const t = {};
  for (let s = 0; s < e.length; s++) {
    const n = e[s];
    for (const r in n)
      if (r === "class")
        t.class !== n.class && (t.class = pe([t.class, n.class]));
      else if (r === "style")
        t.style = An([t.style, n.style]);
      else if (Is(r)) {
        const i = t[r], o = n[r];
        o && i !== o && !(W(i) && i.includes(o)) && (t[r] = i ? [].concat(i, o) : o);
      } else r !== "" && (t[r] = n[r]);
  }
  return t;
}
function Be(e, t, s, n = null) {
  Re(e, t, 7, [
    s,
    n
  ]);
}
const Jl = Ei();
let zl = 0;
function Zl(e, t, s) {
  const n = e.type, r = (t ? t.appContext : e.appContext) || Jl, i = {
    uid: zl++,
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
    scope: new xo(
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
    propsOptions: ji(n, r),
    emitsOptions: Ii(n, r),
    // emit
    emit: null,
    // to be set immediately
    emitted: null,
    // props default value
    propsDefaults: ne,
    // inheritAttrs
    inheritAttrs: n.inheritAttrs,
    // state
    ctx: ne,
    data: ne,
    props: ne,
    attrs: ne,
    slots: ne,
    refs: ne,
    setupState: ne,
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
  return i.ctx = { _: i }, i.root = t ? t.root : i, i.emit = Sl.bind(null, i), e.ce && e.ce(i), i;
}
let Ce = null;
const Ji = () => Ce || Ie;
let Ls, bn;
{
  const e = Os(), t = (s, n) => {
    let r;
    return (r = e[s]) || (r = e[s] = []), r.push(n), (i) => {
      r.length > 1 ? r.forEach((o) => o(i)) : r[0](i);
    };
  };
  Ls = t(
    "__VUE_INSTANCE_SETTERS__",
    (s) => Ce = s
  ), bn = t(
    "__VUE_SSR_SETTERS__",
    (s) => rs = s
  );
}
const us = (e) => {
  const t = Ce;
  return Ls(e), e.scope.on(), () => {
    e.scope.off(), Ls(t);
  };
}, _r = () => {
  Ce && Ce.scope.off(), Ls(null);
};
function zi(e) {
  return e.vnode.shapeFlag & 4;
}
let rs = !1;
function Yl(e, t = !1, s = !1) {
  t && bn(t);
  const { props: n, children: r } = e.vnode, i = zi(e);
  Il(e, n, i, t), Dl(e, r, s || t);
  const o = i ? Ql(e, t) : void 0;
  return t && bn(!1), o;
}
function Ql(e, t) {
  const s = e.type;
  e.accessCache = /* @__PURE__ */ Object.create(null), e.proxy = new Proxy(e.ctx, vl);
  const { setup: n } = s;
  if (n) {
    ct();
    const r = e.setupContext = n.length > 1 ? ec(e) : null, i = us(e), o = cs(
      n,
      e,
      0,
      [
        e.props,
        r
      ]
    ), l = Nr(o);
    if (at(), i(), (l || e.sp) && !Zt(e) && $i(e), l) {
      if (o.then(_r, _r), t)
        return o.then((c) => {
          mr(e, c);
        }).catch((c) => {
          js(c, e, 0);
        });
      e.asyncDep = o;
    } else
      mr(e, o);
  } else
    Zi(e);
}
function mr(e, t, s) {
  G(t) ? e.type.__ssrInlineRender ? e.ssrRender = t : e.render = t : ee(t) && (e.setupState = ai(t)), Zi(e);
}
function Zi(e, t, s) {
  const n = e.type;
  e.render || (e.render = n.render || ze);
  {
    const r = us(e);
    ct();
    try {
      yl(e);
    } finally {
      at(), r();
    }
  }
}
const Xl = {
  get(e, t) {
    return ve(e, "get", ""), e[t];
  }
};
function ec(e) {
  const t = (s) => {
    e.exposed = s || {};
  };
  return {
    attrs: new Proxy(e.attrs, Xl),
    slots: e.slots,
    emit: e.emit,
    expose: t
  };
}
function Us(e) {
  return e.exposed ? e.exposeProxy || (e.exposeProxy = new Proxy(ai(Vo(e.exposed)), {
    get(t, s) {
      if (s in t)
        return t[s];
      if (s in Yt)
        return Yt[s](e);
    },
    has(t, s) {
      return s in t || s in Yt;
    }
  })) : e.proxy;
}
function tc(e) {
  return G(e) && "__vccOpts" in e;
}
const Me = (e, t) => /* @__PURE__ */ qo(e, t, rs);
function Yi(e, t, s) {
  try {
    Ms(-1);
    const n = arguments.length;
    return n === 2 ? ee(t) && !W(t) ? Ps(t) ? ke(e, null, [t]) : ke(e, t) : ke(e, null, t) : (n > 3 ? s = Array.prototype.slice.call(arguments, 2) : n === 3 && Ps(s) && (s = [s]), ke(e, t, s));
  } finally {
    Ms(1);
  }
}
const sc = "3.5.30";
/**
* @vue/runtime-dom v3.5.30
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
let wn;
const vr = typeof window < "u" && window.trustedTypes;
if (vr)
  try {
    wn = /* @__PURE__ */ vr.createPolicy("vue", {
      createHTML: (e) => e
    });
  } catch {
  }
const Qi = wn ? (e) => wn.createHTML(e) : (e) => e, nc = "http://www.w3.org/2000/svg", rc = "http://www.w3.org/1998/Math/MathML", rt = typeof document < "u" ? document : null, yr = rt && /* @__PURE__ */ rt.createElement("template"), ic = {
  insert: (e, t, s) => {
    t.insertBefore(e, s || null);
  },
  remove: (e) => {
    const t = e.parentNode;
    t && t.removeChild(e);
  },
  createElement: (e, t, s, n) => {
    const r = t === "svg" ? rt.createElementNS(nc, e) : t === "mathml" ? rt.createElementNS(rc, e) : s ? rt.createElement(e, { is: s }) : rt.createElement(e);
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
      yr.innerHTML = Qi(
        n === "svg" ? `<svg>${e}</svg>` : n === "mathml" ? `<math>${e}</math>` : e
      );
      const l = yr.content;
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
}, dt = "transition", Vt = "animation", is = /* @__PURE__ */ Symbol("_vtc"), Xi = {
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
}, oc = /* @__PURE__ */ ge(
  {},
  bi,
  Xi
), lc = (e) => (e.displayName = "Transition", e.props = oc, e), eo = /* @__PURE__ */ lc(
  (e, { slots: t }) => Yi(ol, cc(e), t)
), xt = (e, t = []) => {
  W(e) ? e.forEach((s) => s(...t)) : e && e(...t);
}, xr = (e) => e ? W(e) ? e.some((t) => t.length > 1) : e.length > 1 : !1;
function cc(e) {
  const t = {};
  for (const O in e)
    O in Xi || (t[O] = e[O]);
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
    leaveActiveClass: _ = `${s}-leave-active`,
    leaveToClass: k = `${s}-leave-to`
  } = e, F = ac(r), E = F && F[0], j = F && F[1], {
    onBeforeEnter: P,
    onEnter: T,
    onEnterCancelled: z,
    onLeave: R,
    onLeaveCancelled: Z,
    onBeforeAppear: te = P,
    onAppear: ce = T,
    onAppearCancelled: H = z
  } = t, $ = (O, le, _e, Xe) => {
    O._enterCancelled = Xe, bt(O, le ? a : l), bt(O, le ? u : o), _e && _e();
  }, S = (O, le) => {
    O._isLeaving = !1, bt(O, d), bt(O, k), bt(O, _), le && le();
  }, B = (O) => (le, _e) => {
    const Xe = O ? ce : T, he = () => $(le, O, _e);
    xt(Xe, [le, he]), br(() => {
      bt(le, O ? c : i), tt(le, O ? a : l), xr(Xe) || wr(le, n, E, he);
    });
  };
  return ge(t, {
    onBeforeEnter(O) {
      xt(P, [O]), tt(O, i), tt(O, o);
    },
    onBeforeAppear(O) {
      xt(te, [O]), tt(O, c), tt(O, u);
    },
    onEnter: B(!1),
    onAppear: B(!0),
    onLeave(O, le) {
      O._isLeaving = !0;
      const _e = () => S(O, le);
      tt(O, d), O._enterCancelled ? (tt(O, _), Tr(O)) : (Tr(O), tt(O, _)), br(() => {
        O._isLeaving && (bt(O, d), tt(O, k), xr(R) || wr(O, n, j, _e));
      }), xt(R, [O, _e]);
    },
    onEnterCancelled(O) {
      $(O, !1, void 0, !0), xt(z, [O]);
    },
    onAppearCancelled(O) {
      $(O, !0, void 0, !0), xt(H, [O]);
    },
    onLeaveCancelled(O) {
      S(O), xt(Z, [O]);
    }
  });
}
function ac(e) {
  if (e == null)
    return null;
  if (ee(e))
    return [tn(e.enter), tn(e.leave)];
  {
    const t = tn(e);
    return [t, t];
  }
}
function tn(e) {
  return fo(e);
}
function tt(e, t) {
  t.split(/\s+/).forEach((s) => s && e.classList.add(s)), (e[is] || (e[is] = /* @__PURE__ */ new Set())).add(t);
}
function bt(e, t) {
  t.split(/\s+/).forEach((n) => n && e.classList.remove(n));
  const s = e[is];
  s && (s.delete(t), s.size || (e[is] = void 0));
}
function br(e) {
  requestAnimationFrame(() => {
    requestAnimationFrame(e);
  });
}
let uc = 0;
function wr(e, t, s, n) {
  const r = e._endId = ++uc, i = () => {
    r === e._endId && n();
  };
  if (s != null)
    return setTimeout(i, s);
  const { type: o, timeout: l, propCount: c } = fc(e, t);
  if (!o)
    return n();
  const u = o + "end";
  let a = 0;
  const d = () => {
    e.removeEventListener(u, _), i();
  }, _ = (k) => {
    k.target === e && ++a >= c && d();
  };
  setTimeout(() => {
    a < c && d();
  }, l + 1), e.addEventListener(u, _);
}
function fc(e, t) {
  const s = window.getComputedStyle(e), n = (F) => (s[F] || "").split(", "), r = n(`${dt}Delay`), i = n(`${dt}Duration`), o = Cr(r, i), l = n(`${Vt}Delay`), c = n(`${Vt}Duration`), u = Cr(l, c);
  let a = null, d = 0, _ = 0;
  t === dt ? o > 0 && (a = dt, d = o, _ = i.length) : t === Vt ? u > 0 && (a = Vt, d = u, _ = c.length) : (d = Math.max(o, u), a = d > 0 ? o > u ? dt : Vt : null, _ = a ? a === dt ? i.length : c.length : 0);
  const k = a === dt && /\b(?:transform|all)(?:,|$)/.test(
    n(`${dt}Property`).toString()
  );
  return {
    type: a,
    timeout: d,
    propCount: _,
    hasTransform: k
  };
}
function Cr(e, t) {
  for (; e.length < t.length; )
    e = e.concat(e);
  return Math.max(...t.map((s, n) => kr(s) + kr(e[n])));
}
function kr(e) {
  return e === "auto" ? 0 : Number(e.slice(0, -1).replace(",", ".")) * 1e3;
}
function Tr(e) {
  return (e ? e.ownerDocument : document).body.offsetHeight;
}
function dc(e, t, s) {
  const n = e[is];
  n && (t = (t ? [t, ...n] : [...n]).join(" ")), t == null ? e.removeAttribute("class") : s ? e.setAttribute("class", t) : e.className = t;
}
const As = /* @__PURE__ */ Symbol("_vod"), to = /* @__PURE__ */ Symbol("_vsh"), hc = {
  // used for prop mismatch check during hydration
  name: "show",
  beforeMount(e, { value: t }, { transition: s }) {
    e[As] = e.style.display === "none" ? "" : e.style.display, s && t ? s.beforeEnter(e) : Wt(e, t);
  },
  mounted(e, { value: t }, { transition: s }) {
    s && t && s.enter(e);
  },
  updated(e, { value: t, oldValue: s }, { transition: n }) {
    !t != !s && (n ? t ? (n.beforeEnter(e), Wt(e, !0), n.enter(e)) : n.leave(e, () => {
      Wt(e, !1);
    }) : Wt(e, t));
  },
  beforeUnmount(e, { value: t }) {
    Wt(e, t);
  }
};
function Wt(e, t) {
  e.style.display = t ? e[As] : "none", e[to] = !t;
}
const pc = /* @__PURE__ */ Symbol(""), gc = /(?:^|;)\s*display\s*:/;
function _c(e, t, s) {
  const n = e.style, r = fe(s);
  let i = !1;
  if (s && !r) {
    if (t)
      if (fe(t))
        for (const o of t.split(";")) {
          const l = o.slice(0, o.indexOf(":")).trim();
          s[l] == null && bs(n, l, "");
        }
      else
        for (const o in t)
          s[o] == null && bs(n, o, "");
    for (const o in s)
      o === "display" && (i = !0), bs(n, o, s[o]);
  } else if (r) {
    if (t !== s) {
      const o = n[pc];
      o && (s += ";" + o), n.cssText = s, i = gc.test(s);
    }
  } else t && e.removeAttribute("style");
  As in e && (e[As] = i ? n.display : "", e[to] && (n.display = "none"));
}
const $r = /\s*!important$/;
function bs(e, t, s) {
  if (W(s))
    s.forEach((n) => bs(e, t, n));
  else if (s == null && (s = ""), t.startsWith("--"))
    e.setProperty(t, s);
  else {
    const n = mc(e, t);
    $r.test(s) ? e.setProperty(
      St(n),
      s.replace($r, ""),
      "important"
    ) : e[n] = s;
  }
}
const Sr = ["Webkit", "Moz", "ms"], sn = {};
function mc(e, t) {
  const s = sn[t];
  if (s)
    return s;
  let n = Fe(t);
  if (n !== "filter" && n in e)
    return sn[t] = n;
  n = Br(n);
  for (let r = 0; r < Sr.length; r++) {
    const i = Sr[r] + n;
    if (i in e)
      return sn[t] = i;
  }
  return t;
}
const Mr = "http://www.w3.org/1999/xlink";
function Pr(e, t, s, n, r, i = vo(t)) {
  n && t.startsWith("xlink:") ? s == null ? e.removeAttributeNS(Mr, t.slice(6, t.length)) : e.setAttributeNS(Mr, t, s) : s == null || i && !Kr(s) ? e.removeAttribute(t) : e.setAttribute(
    t,
    i ? "" : Qe(s) ? String(s) : s
  );
}
function Lr(e, t, s, n, r) {
  if (t === "innerHTML" || t === "textContent") {
    s != null && (e[t] = t === "innerHTML" ? Qi(s) : s);
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
    l === "boolean" ? s = Kr(s) : s == null && l === "string" ? (s = "", o = !0) : l === "number" && (s = 0, o = !0);
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
function vc(e, t, s, n) {
  e.removeEventListener(t, s, n);
}
const Ar = /* @__PURE__ */ Symbol("_vei");
function yc(e, t, s, n, r = null) {
  const i = e[Ar] || (e[Ar] = {}), o = i[t];
  if (n && o)
    o.value = n;
  else {
    const [l, c] = xc(t);
    if (n) {
      const u = i[t] = Cc(
        n,
        r
      );
      kt(e, l, u, c);
    } else o && (vc(e, l, o, c), i[t] = void 0);
  }
}
const Er = /(?:Once|Passive|Capture)$/;
function xc(e) {
  let t;
  if (Er.test(e)) {
    t = {};
    let n;
    for (; n = e.match(Er); )
      e = e.slice(0, e.length - n[0].length), t[n[0].toLowerCase()] = !0;
  }
  return [e[2] === ":" ? e.slice(3) : St(e.slice(2)), t];
}
let nn = 0;
const bc = /* @__PURE__ */ Promise.resolve(), wc = () => nn || (bc.then(() => nn = 0), nn = Date.now());
function Cc(e, t) {
  const s = (n) => {
    if (!n._vts)
      n._vts = Date.now();
    else if (n._vts <= s.attached)
      return;
    Re(
      kc(n, s.value),
      t,
      5,
      [n]
    );
  };
  return s.value = e, s.attached = wc(), s;
}
function kc(e, t) {
  if (W(t)) {
    const s = e.stopImmediatePropagation;
    return e.stopImmediatePropagation = () => {
      s.call(e), e._stopped = !0;
    }, t.map(
      (n) => (r) => !r._stopped && n && n(r)
    );
  } else
    return t;
}
const Ir = (e) => e.charCodeAt(0) === 111 && e.charCodeAt(1) === 110 && // lowercase letter
e.charCodeAt(2) > 96 && e.charCodeAt(2) < 123, Tc = (e, t, s, n, r, i) => {
  const o = r === "svg";
  t === "class" ? dc(e, n, o) : t === "style" ? _c(e, s, n) : Is(t) ? Sn(t) || yc(e, t, s, n, i) : (t[0] === "." ? (t = t.slice(1), !0) : t[0] === "^" ? (t = t.slice(1), !1) : $c(e, t, n, o)) ? (Lr(e, t, n), !e.tagName.includes("-") && (t === "value" || t === "checked" || t === "selected") && Pr(e, t, n, o, i, t !== "value")) : /* #11081 force set props for possible async custom element */ e._isVueCE && // #12408 check if it's declared prop or it's async custom element
  (Sc(e, t) || // @ts-expect-error _def is private
  e._def.__asyncLoader && (/[A-Z]/.test(t) || !fe(n))) ? Lr(e, Fe(t), n, i, t) : (t === "true-value" ? e._trueValue = n : t === "false-value" && (e._falseValue = n), Pr(e, t, n, o));
};
function $c(e, t, s, n) {
  if (n)
    return !!(t === "innerHTML" || t === "textContent" || t in e && Ir(t) && G(s));
  if (t === "spellcheck" || t === "draggable" || t === "translate" || t === "autocorrect" || t === "sandbox" && e.tagName === "IFRAME" || t === "form" || t === "list" && e.tagName === "INPUT" || t === "type" && e.tagName === "TEXTAREA")
    return !1;
  if (t === "width" || t === "height") {
    const r = e.tagName;
    if (r === "IMG" || r === "VIDEO" || r === "CANVAS" || r === "SOURCE")
      return !1;
  }
  return Ir(t) && fe(s) ? !1 : t in e;
}
function Sc(e, t) {
  const s = (
    // @ts-expect-error _def is private
    e._def.props
  );
  if (!s)
    return !1;
  const n = Fe(t);
  return Array.isArray(s) ? s.some((r) => Fe(r) === n) : Object.keys(s).some((r) => Fe(r) === n);
}
const Es = (e) => {
  const t = e.props["onUpdate:modelValue"] || !1;
  return W(t) ? (s) => ms(t, s) : t;
};
function Mc(e) {
  e.target.composing = !0;
}
function Hr(e) {
  const t = e.target;
  t.composing && (t.composing = !1, t.dispatchEvent(new Event("input")));
}
const Ht = /* @__PURE__ */ Symbol("_assign");
function Fr(e, t, s) {
  return t && (e = e.trim()), s && (e = Ln(e)), e;
}
const Pc = {
  created(e, { modifiers: { lazy: t, trim: s, number: n } }, r) {
    e[Ht] = Es(r);
    const i = n || r.props && r.props.type === "number";
    kt(e, t ? "change" : "input", (o) => {
      o.target.composing || e[Ht](Fr(e.value, s, i));
    }), (s || i) && kt(e, "change", () => {
      e.value = Fr(e.value, s, i);
    }), t || (kt(e, "compositionstart", Mc), kt(e, "compositionend", Hr), kt(e, "change", Hr));
  },
  // set value on mounted so it's after min/max for type="range"
  mounted(e, { value: t }) {
    e.value = t ?? "";
  },
  beforeUpdate(e, { value: t, oldValue: s, modifiers: { lazy: n, trim: r, number: i } }, o) {
    if (e[Ht] = Es(o), e.composing) return;
    const l = (i || e.type === "number") && !/^0\d/.test(e.value) ? Ln(e.value) : e.value, c = t ?? "";
    l !== c && (document.activeElement === e && e.type !== "range" && (n && t === s || r && e.value.trim() === c) || (e.value = c));
  }
}, Lc = {
  // #4096 array checkboxes need to be deep traversed
  deep: !0,
  created(e, t, s) {
    e[Ht] = Es(s), kt(e, "change", () => {
      const n = e._modelValue, r = Ac(e), i = e.checked, o = e[Ht];
      if (W(n)) {
        const l = qr(n, r), c = l !== -1;
        if (i && !c)
          o(n.concat(r));
        else if (!i && c) {
          const u = [...n];
          u.splice(l, 1), o(u);
        }
      } else if (Hs(n)) {
        const l = new Set(n);
        i ? l.add(r) : l.delete(r), o(l);
      } else
        o(so(e, i));
    });
  },
  // set initial checked on mount to wait for true-value/false-value
  mounted: Or,
  beforeUpdate(e, t, s) {
    e[Ht] = Es(s), Or(e, t, s);
  }
};
function Or(e, { value: t, oldValue: s }, n) {
  e._modelValue = t;
  let r;
  if (W(t))
    r = qr(t, n.props.value) > -1;
  else if (Hs(t))
    r = t.has(n.props.value);
  else {
    if (t === s) return;
    r = ls(t, so(e, !0));
  }
  e.checked !== r && (e.checked = r);
}
function Ac(e) {
  return "_value" in e ? e._value : e.value;
}
function so(e, t) {
  const s = t ? "_trueValue" : "_falseValue";
  return s in e ? e[s] : t;
}
const Ec = ["ctrl", "shift", "alt", "meta"], Ic = {
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
  exact: (e, t) => Ec.some((s) => e[`${s}Key`] && !t.includes(s))
}, no = (e, t) => {
  if (!e) return e;
  const s = e._withMods || (e._withMods = {}), n = t.join(".");
  return s[n] || (s[n] = ((r, ...i) => {
    for (let o = 0; o < t.length; o++) {
      const l = Ic[t[o]];
      if (l && l(r, t)) return;
    }
    return e(r, ...i);
  }));
}, Hc = /* @__PURE__ */ ge({ patchProp: Tc }, ic);
let Dr;
function Fc() {
  return Dr || (Dr = jl(Hc));
}
const Oc = ((...e) => {
  const t = Fc().createApp(...e), { mount: s } = t;
  return t.mount = (n) => {
    const r = Rc(n);
    if (!r) return;
    const i = t._component;
    !G(i) && !i.render && !i.template && (i.template = r.innerHTML), r.nodeType === 1 && (r.textContent = "");
    const o = s(r, !1, Dc(r));
    return r instanceof Element && (r.removeAttribute("v-cloak"), r.setAttribute("data-v-app", "")), o;
  }, t;
});
function Dc(e) {
  if (e instanceof SVGElement)
    return "svg";
  if (typeof MathMLElement == "function" && e instanceof MathMLElement)
    return "mathml";
}
function Rc(e) {
  return fe(e) ? document.querySelector(e) : e;
}
let pt = "/api/v1", ws = null, ro = 15e3;
function jc({ baseUrl: e, token: t, timeout: s }) {
  pt = e.replace(/\/+$/, ""), t && (ws = t), s && (ro = s);
}
function Rr(e) {
  if (!e) return null;
  if (e.startsWith("http://") || e.startsWith("https://")) return e;
  if (pt.startsWith("http"))
    try {
      return new URL(pt).origin + e;
    } catch {
    }
  return e;
}
async function st(e, t = {}) {
  const s = new URL(e, pt.startsWith("http") ? pt : window.location.origin + pt);
  s.pathname.startsWith(pt.replace(/^https?:\/\/[^/]+/, "")) || (s.pathname = pt.replace(/^https?:\/\/[^/]+/, "") + e);
  const n = {
    Accept: "application/json",
    "Content-Type": "application/json"
  };
  ws && (n.Authorization = `Bearer ${ws}`);
  const r = typeof localStorage < "u" ? localStorage.getItem("pim_token") : null;
  !ws && r && (n.Authorization = `Bearer ${r}`);
  const i = new AbortController(), o = setTimeout(() => i.abort(), ro);
  try {
    const l = await fetch(s.toString(), {
      ...t,
      headers: { ...n, ...t.headers },
      signal: i.signal
    });
    if (clearTimeout(o), !l.ok) {
      const c = new Error(`HTTP ${l.status}`);
      c.status = l.status;
      try {
        c.data = await l.json();
      } catch {
      }
      throw c;
    }
    return l;
  } catch (l) {
    throw clearTimeout(o), l;
  }
}
function Bt(e = {}) {
  const t = new URLSearchParams();
  if (e.page && t.set("page", e.page), e.perPage && t.set("per_page", e.perPage), e.sort && t.set("sort", e.sort), e.order && t.set("order", e.order), e.search && t.set("search", e.search), e.category && t.set("category", e.category), e.hierarchyType && t.set("hierarchy_type", e.hierarchyType), e.lang && t.set("lang", e.lang), e.type && t.set("type", e.type), e.hierarchyId && t.set("hierarchy_id", e.hierarchyId), e.filters)
    for (const [n, r] of Object.entries(e.filters))
      t.set(`filters[${n}]`, r);
  const s = t.toString();
  return s ? `?${s}` : "";
}
const nt = {
  async getProducts(e = {}) {
    const t = await st(`/catalog/products${Bt(e)}`), s = await t.json();
    return {
      products: Array.isArray(s) ? s : s.data || s,
      meta: {
        current_page: parseInt(t.headers.get("x-current-page") || "1", 10),
        last_page: parseInt(t.headers.get("x-last-page") || "1", 10),
        per_page: parseInt(t.headers.get("x-per-page") || "24", 10),
        total: parseInt(t.headers.get("x-total-count") || "0", 10)
      }
    };
  },
  async getProduct(e, t = {}) {
    const n = await (await st(`/catalog/products/${e}${Bt(t)}`)).json();
    return n.data || n;
  },
  async getCategories(e = {}) {
    const s = await (await st(`/catalog/categories${Bt(e)}`)).json();
    return s.data || s;
  },
  async getSettings() {
    const t = await (await st("/catalog/settings")).json();
    return t.data || t;
  },
  async getFacets(e = {}) {
    return await (await st(`/catalog/facets${Bt(e)}`)).json();
  },
  async downloadProductPdf(e, t = {}) {
    return (await st(`/catalog/products/${e}/pdf${Bt(t)}`)).blob();
  },
  async downloadWishlistPdf(e, t) {
    return (await st("/catalog/wishlist/pdf", {
      method: "POST",
      body: JSON.stringify({ product_ids: e, lang: t })
    })).blob();
  },
  async downloadWishlistExcel(e) {
    return (await st("/catalog/wishlist/excel", {
      method: "POST",
      body: JSON.stringify({ product_ids: e })
    })).blob();
  },
  async compareProducts(e, t) {
    const n = await (await st("/catalog/products/compare", {
      method: "POST",
      body: JSON.stringify({ product_ids: e, lang: t })
    })).json();
    return n.data || n;
  }
};
function Nc() {
  const e = /* @__PURE__ */ Rs({
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
    viewMode: localStorage.getItem("pxc_view_mode") || "grid",
    locale: localStorage.getItem("pxc_locale") || "de",
    // Categories
    categories: [],
    hierarchyInfo: null,
    categoriesLoading: !1,
    // Facets
    facets: [],
    activeFilters: {},
    // Wishlist
    wishlistIds: JSON.parse(localStorage.getItem("pxc_wishlist") || "[]"),
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
    detailProductId: null
  });
  Ye(() => e.wishlistIds, (n) => {
    localStorage.setItem("pxc_wishlist", JSON.stringify(n));
  }, { deep: !0 }), Ye(() => e.viewMode, (n) => localStorage.setItem("pxc_view_mode", n)), Ye(() => e.locale, (n) => localStorage.setItem("pxc_locale", n));
  const t = {
    isEmpty: Me(() => e.products.length === 0 && !e.loading),
    wishlistCount: Me(() => e.wishlistIds.length),
    searchActive: Me(() => e.search && e.search.trim().length > 0),
    activeFilterCount: Me(() => Object.keys(e.activeFilters).length),
    isInWishlist(n) {
      return e.wishlistIds.includes(n);
    }
  }, s = {
    async fetchSettings() {
      try {
        const n = await nt.getSettings();
        e.settings = n || {}, !localStorage.getItem("pxc_locale") && n.default_locale && (e.locale = n.default_locale), e._settingsLoaded = !0;
      } catch (n) {
        console.warn("[PublixxCatalog] Failed to load settings:", n.message);
      }
    },
    async fetchProducts() {
      var n;
      e.loading = !0, e.error = null;
      try {
        const r = e.search && e.search.trim().length > 0, i = await nt.getProducts({
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
          image_url: Rr(o.image_url)
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
        const i = await nt.getProduct(n, { lang: e.locale });
        i != null && i.media && (i.media = i.media.map((o) => ({ ...o, url: Rr(o.url) }))), e.currentProduct = i;
      } catch (i) {
        e.error = ((r = i.data) == null ? void 0 : r.title) || "Produkt nicht gefunden", e.currentProduct = null;
      } finally {
        e.productLoading = !1;
      }
    },
    async fetchCategories() {
      e.categoriesLoading = !0;
      try {
        const n = await nt.getCategories({
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
        const n = await nt.getFacets({ lang: e.locale });
        e.facets = n.facets || [];
      } catch (n) {
        console.warn("[PublixxCatalog] Facets load failed:", n.message), e.facets = [];
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
      e.locale = n;
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
      e.wishlistIds = [];
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
        e.compareData = await nt.compareProducts(e.compareProductIds, e.locale);
      } catch (r) {
        console.error("[PublixxCatalog] Compare failed:", r), e.compareData = null;
      } finally {
        e.compareLoading = !1;
      }
    },
    closeCompare() {
      e.compareOpen = !1, e.compareData = null, e.compareProductIds = [];
    },
    // Exports
    async downloadProductPdf(n) {
      const r = await nt.downloadProductPdf(n, { lang: e.locale });
      rn(r, `product-${n}.pdf`);
    },
    async downloadWishlistPdf() {
      const n = await nt.downloadWishlistPdf(e.wishlistIds, e.locale);
      rn(n, `wishlist-${(/* @__PURE__ */ new Date()).toISOString().slice(0, 10)}.pdf`);
    },
    async downloadWishlistExcel() {
      const n = await nt.downloadWishlistExcel(e.wishlistIds);
      rn(n, `wishlist-${(/* @__PURE__ */ new Date()).toISOString().slice(0, 10)}.xlsx`);
    }
  };
  return { state: e, getters: t, actions: s };
}
function rn(e, t) {
  const s = URL.createObjectURL(e), n = document.createElement("a");
  n.href = s, n.download = t, document.body.appendChild(n), n.click(), n.remove(), URL.revokeObjectURL(s);
}
let on = null;
function Pe() {
  return on || (on = Nc()), on;
}
const K = {
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
  loader: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pxc-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>'
}, Vc = { class: "pxc-search__wrapper" }, Wc = ["innerHTML"], Bc = ["value"], Uc = ["innerHTML"], Kc = ["innerHTML"], qc = {
  __name: "SearchWidget",
  setup(e) {
    const { state: t, actions: s } = Pe(), n = /* @__PURE__ */ Ze(t.search);
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
    return (c, u) => (m(), v("form", {
      class: "pxc-search",
      onSubmit: l
    }, [
      g("div", Vc, [
        g("span", {
          class: "pxc-search__icon",
          innerHTML: p(K).search
        }, null, 8, Wc),
        g("input", {
          type: "text",
          class: "pxc-search__input",
          value: n.value,
          placeholder: "Produkte suchen...",
          onInput: i
        }, null, 40, Bc),
        n.value ? (m(), v("button", {
          key: 0,
          type: "button",
          class: "pxc-search__clear",
          onClick: o,
          innerHTML: p(K).x
        }, null, 8, Uc)) : V("", !0),
        p(t).loading ? (m(), v("span", {
          key: 1,
          class: "pxc-search__loader",
          innerHTML: p(K).loader
        }, null, 8, Kc)) : V("", !0)
      ])
    ], 32));
  }
}, Gc = { class: "pxc-categories" }, Jc = { class: "pxc-categories__header" }, zc = ["innerHTML"], Zc = { class: "pxc-categories__count" }, Yc = {
  key: 0,
  class: "pxc-categories__loading"
}, Qc = { class: "pxc-categories__row" }, Xc = ["onClick", "innerHTML"], ea = {
  key: 1,
  class: "pxc-categories__toggle-space"
}, ta = ["onClick"], sa = {
  key: 0,
  class: "pxc-categories__count"
}, na = { class: "pxc-categories__row" }, ra = ["onClick", "innerHTML"], ia = {
  key: 1,
  class: "pxc-categories__toggle-space"
}, oa = ["onClick"], la = {
  key: 0,
  class: "pxc-categories__count"
}, ca = { class: "pxc-categories__row" }, aa = ["onClick"], ua = {
  key: 0,
  class: "pxc-categories__count"
}, fa = {
  __name: "CategoriesWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Pe(), r = /* @__PURE__ */ Ze({});
    as(() => {
      t.categories.length === 0 && s.fetchCategories();
    }), Ye(() => t.locale, () => s.fetchCategories());
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
    return (u, a) => (m(), v("div", Gc, [
      g("div", Jc, [
        g("span", {
          innerHTML: p(K).folder
        }, null, 8, zc),
        a[0] || (a[0] = g("span", null, "Kategorien", -1))
      ]),
      g("button", {
        class: pe(["pxc-categories__item", { "pxc-categories__item--active": !p(t).selectedCategoryId }]),
        onClick: o
      }, [
        a[1] || (a[1] = de(" Alle Kategorien ", -1)),
        g("span", Zc, D(p(t).meta.total), 1)
      ], 2),
      p(t).categoriesLoading ? (m(), v("div", Yc, [
        (m(), v(J, null, ue(5, (d) => g("div", {
          key: d,
          class: "pxc-skeleton",
          style: { height: "24px", "margin-bottom": "4px" }
        })), 64))
      ])) : (m(!0), v(J, { key: 1 }, ue(p(t).categories, (d) => (m(), v("div", {
        key: d.id,
        class: "pxc-categories__node"
      }, [
        g("div", Qc, [
          d.children && d.children.length ? (m(), v("button", {
            key: 0,
            class: "pxc-categories__toggle",
            onClick: (_) => l(d.id),
            innerHTML: c(d.id) ? p(K).chevronDown : p(K).chevronRight
          }, null, 8, Xc)) : (m(), v("span", ea)),
          g("button", {
            class: pe(["pxc-categories__item", { "pxc-categories__item--active": p(t).selectedCategoryId === d.id }]),
            onClick: (_) => i(d)
          }, [
            de(D(d.name) + " ", 1),
            d.product_count ? (m(), v("span", sa, D(d.product_count), 1)) : V("", !0)
          ], 10, ta)
        ]),
        c(d.id) && d.children ? (m(!0), v(J, { key: 0 }, ue(d.children, (_) => (m(), v("div", {
          key: _.id,
          class: "pxc-categories__node pxc-categories__node--l1"
        }, [
          g("div", na, [
            _.children && _.children.length ? (m(), v("button", {
              key: 0,
              class: "pxc-categories__toggle",
              onClick: (k) => l(_.id),
              innerHTML: c(_.id) ? p(K).chevronDown : p(K).chevronRight
            }, null, 8, ra)) : (m(), v("span", ia)),
            g("button", {
              class: pe(["pxc-categories__item", { "pxc-categories__item--active": p(t).selectedCategoryId === _.id }]),
              onClick: (k) => i(_)
            }, [
              de(D(_.name) + " ", 1),
              _.product_count ? (m(), v("span", la, D(_.product_count), 1)) : V("", !0)
            ], 10, oa)
          ]),
          c(_.id) && _.children ? (m(!0), v(J, { key: 0 }, ue(_.children, (k) => (m(), v("div", {
            key: k.id,
            class: "pxc-categories__node pxc-categories__node--l2"
          }, [
            g("div", ca, [
              a[2] || (a[2] = g("span", { class: "pxc-categories__toggle-space" }, null, -1)),
              g("button", {
                class: pe(["pxc-categories__item", { "pxc-categories__item--active": p(t).selectedCategoryId === k.id }]),
                onClick: (F) => i(k)
              }, [
                de(D(k.name) + " ", 1),
                k.product_count ? (m(), v("span", ua, D(k.product_count), 1)) : V("", !0)
              ], 10, aa)
            ])
          ]))), 128)) : V("", !0)
        ]))), 128)) : V("", !0)
      ]))), 128))
    ]));
  }
}, da = {
  key: 0,
  class: "pxc-facets"
}, ha = { class: "pxc-facets__header" }, pa = ["onClick"], ga = ["innerHTML"], _a = { class: "pxc-facets__group-label" }, ma = {
  key: 0,
  class: "pxc-facets__badge"
}, va = { class: "pxc-facets__body" }, ya = {
  key: 0,
  class: "pxc-facets__search"
}, xa = ["onUpdate:modelValue"], ba = ["checked", "onChange"], wa = { class: "pxc-facets__checkbox-label" }, Ca = { class: "pxc-facets__checkbox-count" }, ka = ["onClick"], Ta = ["onClick"], $a = {
  key: 1,
  class: "pxc-facets__toggle"
}, Sa = ["checked", "onChange"], Ma = {
  key: 2,
  class: "pxc-facets__range"
}, Pa = { class: "pxc-facets__range-field" }, La = ["placeholder", "value", "onChange"], Aa = { class: "pxc-facets__range-field" }, Ea = ["placeholder", "value", "onChange"], Ia = {
  key: 0,
  class: "pxc-facets__range-unit"
}, ln = 5, Ha = {
  __name: "FacetsWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Pe(), r = /* @__PURE__ */ Ze({}), i = /* @__PURE__ */ Ze({}), o = /* @__PURE__ */ Ze({});
    as(() => {
      t.facets.length === 0 && s.fetchFacets();
    }), Ye(() => t.locale, () => s.fetchFacets());
    function l(H) {
      r.value[H] = !r.value[H];
    }
    function c(H) {
      return !!r.value[H];
    }
    function u(H) {
      i.value[H] = !i.value[H];
    }
    function a(H) {
      return !!i.value[H];
    }
    function d(H) {
      const $ = t.activeFilters[H];
      return $ ? String($).split(",").filter(Boolean) : [];
    }
    function _(H, $) {
      const S = d(H), B = S.indexOf(String($));
      B === -1 ? S.push(String($)) : S.splice(B, 1), S.length === 0 ? s.clearFilter(H) : s.setFilter(H, S.join(",")), s.fetchProducts();
    }
    function k(H, $) {
      return d(H).includes(String($));
    }
    function F(H) {
      t.activeFilters[H] === "1" ? s.clearFilter(H) : s.setFilter(H, "1"), s.fetchProducts();
    }
    function E(H) {
      const $ = t.activeFilters[H];
      if (!$) return { min: "", max: "" };
      const S = String($).split(":");
      return { min: S[0] || "", max: S[1] || "" };
    }
    function j(H, $) {
      !$.min && !$.max ? s.clearFilter(H) : s.setFilter(H, `${$.min}:${$.max}`), s.fetchProducts();
    }
    function P(H, $) {
      j(H, { ...E(H), min: $ });
    }
    function T(H, $) {
      j(H, { ...E(H), max: $ });
    }
    function z(H) {
      const $ = H.values || [], S = (o.value[H.attribute_id] || "").toLowerCase();
      return S ? $.filter((B) => B.value.toLowerCase().includes(S)) : $;
    }
    function R(H) {
      const $ = z(H);
      return a(H.attribute_id) ? $ : $.slice(0, ln);
    }
    function Z(H) {
      return z(H).length - ln;
    }
    function te(H) {
      return d(H).length;
    }
    function ce() {
      s.clearAllFilters(), s.fetchProducts();
    }
    return (H, $) => p(t).facets.length ? (m(), v("div", da, [
      g("div", ha, [
        $[0] || ($[0] = g("span", { class: "pxc-facets__title" }, "Filter", -1)),
        p(n).activeFilterCount.value > 0 ? (m(), v("button", {
          key: 0,
          class: "pxc-facets__clear-all",
          onClick: ce
        }, "Alle zurücksetzen")) : V("", !0)
      ]),
      (m(!0), v(J, null, ue(p(t).facets, (S) => (m(), v("div", {
        key: S.attribute_id,
        class: "pxc-facets__group"
      }, [
        g("button", {
          class: "pxc-facets__group-header",
          onClick: (B) => l(S.attribute_id)
        }, [
          g("span", {
            innerHTML: c(S.attribute_id) ? p(K).chevronRight : p(K).chevronDown
          }, null, 8, ga),
          g("span", _a, D(S.label), 1),
          te(S.attribute_id) > 0 ? (m(), v("span", ma, D(te(S.attribute_id)), 1)) : V("", !0)
        ], 8, pa),
        hn(g("div", va, [
          S.data_type === "ValueList" || S.data_type === "Text" ? (m(), v(J, { key: 0 }, [
            (S.values || []).length > 8 ? (m(), v("div", ya, [
              hn(g("input", {
                "onUpdate:modelValue": (B) => o.value[S.attribute_id] = B,
                type: "text",
                placeholder: "Suchen...",
                class: "pxc-facets__search-input"
              }, null, 8, xa), [
                [Pc, o.value[S.attribute_id]]
              ])
            ])) : V("", !0),
            (m(!0), v(J, null, ue(R(S), (B) => (m(), v("label", {
              key: B.value_id || B.value,
              class: "pxc-facets__checkbox"
            }, [
              g("input", {
                type: "checkbox",
                checked: k(S.attribute_id, B.value_id || B.value),
                onChange: (O) => _(S.attribute_id, B.value_id || B.value)
              }, null, 40, ba),
              g("span", wa, D(B.value), 1),
              g("span", Ca, D(B.count), 1)
            ]))), 128)),
            Z(S) > 0 && !a(S.attribute_id) ? (m(), v("button", {
              key: 1,
              class: "pxc-facets__show-more",
              onClick: (B) => u(S.attribute_id)
            }, "Mehr anzeigen (+" + D(Z(S)) + ")", 9, ka)) : a(S.attribute_id) && (S.values || []).length > ln ? (m(), v("button", {
              key: 2,
              class: "pxc-facets__show-more",
              onClick: (B) => u(S.attribute_id)
            }, "Weniger anzeigen", 8, Ta)) : V("", !0)
          ], 64)) : S.data_type === "Boolean" ? (m(), v("label", $a, [
            g("input", {
              type: "checkbox",
              checked: p(t).activeFilters[S.attribute_id] === "1",
              onChange: (B) => F(S.attribute_id)
            }, null, 40, Sa),
            g("span", null, D(S.label), 1)
          ])) : S.data_type === "Decimal" || S.data_type === "Integer" ? (m(), v("div", Ma, [
            g("div", Pa, [
              $[1] || ($[1] = g("label", null, "Von", -1)),
              g("input", {
                type: "number",
                placeholder: S.min != null ? String(S.min) : "",
                value: E(S.attribute_id).min,
                onChange: (B) => P(S.attribute_id, B.target.value)
              }, null, 40, La)
            ]),
            $[3] || ($[3] = g("span", { class: "pxc-facets__range-sep" }, "–", -1)),
            g("div", Aa, [
              $[2] || ($[2] = g("label", null, "Bis", -1)),
              g("input", {
                type: "number",
                placeholder: S.max != null ? String(S.max) : "",
                value: E(S.attribute_id).max,
                onChange: (B) => T(S.attribute_id, B.target.value)
              }, null, 40, Ea)
            ]),
            S.unit ? (m(), v("span", Ia, D(S.unit), 1)) : V("", !0)
          ])) : V("", !0)
        ], 512), [
          [hc, !c(S.attribute_id)]
        ])
      ]))), 128))
    ])) : V("", !0);
  }
}, Fa = { class: "pxc-product-grid" }, Oa = {
  key: 0,
  class: "pxc-product-grid__loading"
}, Da = {
  key: 1,
  class: "pxc-product-grid__empty"
}, Ra = ["innerHTML"], ja = ["onClick"], Na = { class: "pxc-product-card__image" }, Va = ["src", "alt"], Wa = {
  key: 1,
  class: "pxc-product-card__no-image"
}, Ba = ["innerHTML"], Ua = ["onClick", "title"], Ka = ["innerHTML"], qa = { class: "pxc-product-card__body" }, Ga = {
  key: 0,
  class: "pxc-product-card__category"
}, Ja = { class: "pxc-product-card__name" }, za = {
  key: 1,
  class: "pxc-product-card__sku"
}, Za = {
  key: 2,
  class: "pxc-product-card__attrs"
}, Ya = {
  key: 3,
  class: "pxc-product-card__price"
}, Qa = {
  key: 3,
  class: "pxc-product-grid__overlay"
}, Xa = ["innerHTML"], eu = {
  __name: "ProductGridWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Pe();
    as(() => {
      t.products.length === 0 && !t.loading && s.fetchProducts();
    }), Ye(() => t.locale, () => s.fetchProducts());
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
    return (l, c) => (m(), v("div", Fa, [
      p(t).loading && p(t).products.length === 0 ? (m(), v("div", Oa, [
        (m(), v(J, null, ue(8, (u) => g("div", {
          key: u,
          class: "pxc-skeleton pxc-skeleton--card"
        })), 64))
      ])) : p(n).isEmpty.value ? (m(), v("div", Da, [
        g("span", {
          innerHTML: p(K).package,
          style: { width: "48px", height: "48px", opacity: "0.2" }
        }, null, 8, Ra),
        c[0] || (c[0] = g("p", null, "Keine Produkte gefunden", -1))
      ])) : (m(), v("div", {
        key: 2,
        class: pe(["pxc-product-grid__grid", p(t).viewMode === "list" ? "pxc-product-grid__grid--list" : ""])
      }, [
        (m(!0), v(J, null, ue(p(t).products, (u) => {
          var a;
          return m(), v("div", {
            key: u.id,
            class: "pxc-product-card",
            onClick: (d) => i(u)
          }, [
            g("div", Na, [
              u.image_url ? (m(), v("img", {
                key: 0,
                src: u.image_url,
                alt: u.name,
                loading: "lazy"
              }, null, 8, Va)) : (m(), v("div", Wa, [
                g("span", {
                  innerHTML: p(K).package
                }, null, 8, Ba)
              ])),
              g("button", {
                class: "pxc-product-card__wishlist",
                onClick: (d) => o(d, u.id),
                title: p(n).isInWishlist(u.id) ? "Von Merkliste entfernen" : "Zur Merkliste"
              }, [
                g("span", {
                  innerHTML: p(n).isInWishlist(u.id) ? p(K).heartFilled : p(K).heart,
                  class: pe({ "pxc-text-accent": p(n).isInWishlist(u.id) })
                }, null, 10, Ka)
              ], 8, Ua)
            ]),
            g("div", qa, [
              u.category_path ? (m(), v("p", Ga, D(u.category_path), 1)) : V("", !0),
              g("h3", Ja, D(u.primary_attribute_value || u.name || u.sku || "–"), 1),
              u.sku ? (m(), v("p", za, D(u.sku), 1)) : V("", !0),
              (a = u.card_attributes) != null && a.length ? (m(), v("div", Za, [
                (m(!0), v(J, null, ue(u.card_attributes.slice(0, 3), (d, _) => (m(), v("span", { key: _ }, D(d.value), 1))), 128))
              ])) : V("", !0),
              u.price ? (m(), v("div", Ya, D(r(u.price, u.currency)), 1)) : V("", !0)
            ])
          ], 8, ja);
        }), 128))
      ], 2)),
      p(t).loading && p(t).products.length > 0 ? (m(), v("div", Qa, [
        g("span", {
          innerHTML: p(K).loader,
          style: { width: "32px", height: "32px" }
        }, null, 8, Xa)
      ])) : V("", !0)
    ]));
  }
}, tu = {
  key: 0,
  class: "pxc-pagination"
}, su = { class: "pxc-pagination__info" }, nu = { class: "pxc-pagination__buttons" }, ru = ["disabled"], iu = ["innerHTML"], ou = {
  key: 0,
  disabled: "",
  class: "pxc-pagination__dots"
}, lu = ["onClick"], cu = ["disabled"], au = ["innerHTML"], uu = {
  __name: "PaginationWidget",
  setup(e) {
    const { state: t, actions: s } = Pe(), n = Me(() => {
      const { current_page: o, last_page: l } = t.meta;
      if (l <= 1) return [];
      const c = [], u = 5;
      let a = Math.max(1, o - Math.floor(u / 2)), d = Math.min(l, a + u - 1);
      a = Math.max(1, d - u + 1), a > 1 && (c.push(1), a > 2 && c.push("..."));
      for (let _ = a; _ <= d; _++) c.push(_);
      return d < l && (d < l - 1 && c.push("..."), c.push(l)), c;
    }), r = Me(() => {
      const { current_page: o, per_page: l, total: c } = t.meta;
      return {
        from: (o - 1) * l + 1,
        to: Math.min(o * l, c)
      };
    });
    function i(o) {
      typeof o == "number" && (s.setPage(o), s.fetchProducts(), window.scrollTo({ top: 0, behavior: "smooth" }));
    }
    return (o, l) => p(t).meta.last_page > 1 ? (m(), v("div", tu, [
      g("p", su, D(r.value.from) + "–" + D(r.value.to) + " von " + D(p(t).meta.total), 1),
      g("div", nu, [
        g("button", {
          disabled: p(t).meta.current_page <= 1,
          onClick: l[0] || (l[0] = (c) => i(p(t).meta.current_page - 1))
        }, [
          g("span", {
            innerHTML: p(K).chevronLeft
          }, null, 8, iu)
        ], 8, ru),
        (m(!0), v(J, null, ue(n.value, (c, u) => (m(), v(J, { key: u }, [
          c === "..." ? (m(), v("button", ou, "...")) : (m(), v("button", {
            key: 1,
            class: pe({ "pxc-pagination__active": c === p(t).meta.current_page }),
            onClick: (a) => i(c)
          }, D(c), 11, lu))
        ], 64))), 128)),
        g("button", {
          disabled: p(t).meta.current_page >= p(t).meta.last_page,
          onClick: l[1] || (l[1] = (c) => i(p(t).meta.current_page + 1))
        }, [
          g("span", {
            innerHTML: p(K).chevronRight
          }, null, 8, au)
        ], 8, cu)
      ])
    ])) : V("", !0);
  }
}, fu = { class: "pxc-toolbar" }, du = { class: "pxc-toolbar__count" }, hu = { class: "pxc-toolbar__actions" }, pu = { class: "pxc-toolbar__sort" }, gu = ["value"], _u = ["title"], mu = ["innerHTML"], vu = { class: "pxc-toolbar__view" }, yu = ["innerHTML"], xu = ["innerHTML"], bu = {
  __name: "ToolbarWidget",
  setup(e) {
    const { state: t, actions: s } = Pe();
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
    return (o, l) => (m(), v("div", fu, [
      g("span", du, [
        de(D(p(t).meta.total) + " Produkte ", 1),
        p(t).selectedCategoryName ? (m(), v(J, { key: 0 }, [
          l[2] || (l[2] = de(" in ", -1)),
          g("strong", null, D(p(t).selectedCategoryName), 1)
        ], 64)) : V("", !0)
      ]),
      g("div", hu, [
        g("div", pu, [
          g("select", {
            value: p(t).sort.field,
            onChange: r
          }, [...l[3] || (l[3] = [
            g("option", { value: "name" }, "Name", -1),
            g("option", { value: "sku" }, "Artikelnummer", -1),
            g("option", { value: "created_at" }, "Neu", -1),
            g("option", { value: "updated_at" }, "Aktualisiert", -1)
          ])], 40, gu),
          g("button", {
            onClick: n,
            title: p(t).sort.order === "asc" ? "Aufsteigend" : "Absteigend"
          }, [
            g("span", {
              innerHTML: p(t).sort.order === "asc" ? p(K).sortAsc : p(K).sortDesc
            }, null, 8, mu)
          ], 8, _u)
        ]),
        g("div", vu, [
          g("button", {
            class: pe({ "pxc-toolbar__view--active": p(t).viewMode === "grid" }),
            onClick: l[0] || (l[0] = (c) => i("grid")),
            innerHTML: p(K).grid
          }, null, 10, yu),
          g("button", {
            class: pe({ "pxc-toolbar__view--active": p(t).viewMode === "list" }),
            onClick: l[1] || (l[1] = (c) => i("list")),
            innerHTML: p(K).list
          }, null, 10, xu)
        ])
      ])
    ]));
  }
}, wu = { class: "pxc-wishlist" }, Cu = ["innerHTML"], ku = {
  key: 0,
  class: "pxc-wishlist__badge"
}, Tu = { class: "pxc-wishlist__drawer-header" }, $u = ["innerHTML"], Su = {
  key: 0,
  class: "pxc-wishlist__badge"
}, Mu = ["innerHTML"], Pu = {
  key: 0,
  class: "pxc-wishlist__empty"
}, Lu = ["innerHTML"], Au = {
  key: 1,
  class: "pxc-wishlist__items"
}, Eu = { class: "pxc-wishlist__item-image" }, Iu = ["src", "alt"], Hu = ["innerHTML"], Fu = { class: "pxc-wishlist__item-info" }, Ou = { class: "pxc-wishlist__item-name" }, Du = { class: "pxc-wishlist__item-sku" }, Ru = {
  key: 0,
  class: "pxc-wishlist__item-price"
}, ju = ["onClick"], Nu = ["innerHTML"], Vu = {
  key: 0,
  class: "pxc-text-muted",
  style: { "text-align": "center", padding: "8px" }
}, Wu = {
  key: 2,
  class: "pxc-wishlist__footer"
}, Bu = ["disabled"], Uu = ["innerHTML"], Ku = ["disabled"], qu = ["innerHTML"], Gu = ["innerHTML"], Ju = ["innerHTML"], zu = ["innerHTML"], Zu = {
  __name: "WishlistWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Pe(), r = /* @__PURE__ */ Ze(!1), i = /* @__PURE__ */ Ze(null), o = /* @__PURE__ */ Ze(!1), l = Me(() => t.products.filter((j) => n.isInWishlist(j.id))), c = Me(() => {
      const j = new Set(t.products.map((P) => P.id));
      return t.wishlistIds.filter((P) => !j.has(P)).length;
    }), u = Me(
      () => t.settings.catalog_compare_enabled && n.wishlistCount.value >= 2 && n.wishlistCount.value <= (t.settings.catalog_compare_max_products || 3)
    );
    function a() {
      r.value = !r.value;
    }
    function d(j) {
      return j ? new Intl.NumberFormat(t.locale === "de" ? "de-DE" : "en-US", {
        style: "currency",
        currency: "EUR"
      }).format(j) : null;
    }
    async function _() {
      if (!i.value) {
        i.value = "pdf";
        try {
          await s.downloadWishlistPdf();
        } catch (j) {
          console.error("PDF export failed:", j);
        } finally {
          i.value = null;
        }
      }
    }
    async function k() {
      if (!i.value) {
        i.value = "excel";
        try {
          await s.downloadWishlistExcel();
        } catch (j) {
          console.error("Excel export failed:", j);
        } finally {
          i.value = null;
        }
      }
    }
    function F() {
      s.openCompare([...t.wishlistIds]);
    }
    async function E() {
      const j = `${window.location.origin}${window.location.pathname}?wishlist=${t.wishlistIds.join(",")}`;
      try {
        await navigator.clipboard.writeText(j), o.value = !0, setTimeout(() => {
          o.value = !1;
        }, 2e3);
      } catch {
      }
    }
    return (j, P) => (m(), v("div", wu, [
      g("button", {
        class: "pxc-wishlist__toggle",
        onClick: a
      }, [
        g("span", {
          innerHTML: p(K).heart
        }, null, 8, Cu),
        p(n).wishlistCount.value > 0 ? (m(), v("span", ku, D(p(n).wishlistCount.value), 1)) : V("", !0)
      ]),
      r.value ? (m(), v("div", {
        key: 0,
        class: "pxc-wishlist__overlay",
        onClick: a
      })) : V("", !0),
      g("div", {
        class: pe(["pxc-wishlist__drawer", { "pxc-wishlist__drawer--open": r.value }])
      }, [
        g("div", Tu, [
          g("span", {
            innerHTML: p(K).heart,
            class: "pxc-text-accent"
          }, null, 8, $u),
          P[1] || (P[1] = g("span", null, "Merkliste", -1)),
          p(n).wishlistCount.value ? (m(), v("span", Su, D(p(n).wishlistCount.value), 1)) : V("", !0),
          g("button", {
            class: "pxc-wishlist__close",
            onClick: a,
            innerHTML: p(K).x
          }, null, 8, Mu)
        ]),
        p(n).wishlistCount.value === 0 ? (m(), v("div", Pu, [
          g("span", {
            innerHTML: p(K).heart,
            style: { width: "40px", height: "40px", opacity: "0.15" }
          }, null, 8, Lu),
          P[2] || (P[2] = g("p", null, "Merkliste ist leer", -1)),
          P[3] || (P[3] = g("p", { class: "pxc-text-muted" }, "Klicken Sie auf das Herz-Symbol bei einem Produkt", -1))
        ])) : (m(), v("div", Au, [
          (m(!0), v(J, null, ue(l.value, (T) => (m(), v("div", {
            key: T.id,
            class: "pxc-wishlist__item"
          }, [
            g("div", Eu, [
              T.image_url ? (m(), v("img", {
                key: 0,
                src: T.image_url,
                alt: T.name
              }, null, 8, Iu)) : (m(), v("span", {
                key: 1,
                innerHTML: p(K).package
              }, null, 8, Hu))
            ]),
            g("div", Fu, [
              g("p", Ou, D(T.name), 1),
              g("p", Du, D(T.sku), 1),
              T.price ? (m(), v("p", Ru, D(d(T.price)), 1)) : V("", !0)
            ]),
            g("button", {
              class: "pxc-wishlist__item-remove",
              onClick: (z) => p(s).toggleWishlist(T.id)
            }, [
              g("span", {
                innerHTML: p(K).trash
              }, null, 8, Nu)
            ], 8, ju)
          ]))), 128)),
          c.value > 0 ? (m(), v("div", Vu, " + " + D(c.value) + " weitere Produkte ", 1)) : V("", !0)
        ])),
        p(n).wishlistCount.value > 0 ? (m(), v("div", Wu, [
          p(t).settings.catalog_pdf_enabled ? (m(), v("button", {
            key: 0,
            class: "pxc-btn pxc-btn--primary",
            onClick: _,
            disabled: !!i.value
          }, [
            g("span", {
              innerHTML: p(K).fileDown
            }, null, 8, Uu),
            de(" " + D(i.value === "pdf" ? "Exportiere..." : "Als PDF"), 1)
          ], 8, Bu)) : V("", !0),
          p(t).settings.catalog_excel_export_enabled ? (m(), v("button", {
            key: 1,
            class: "pxc-btn pxc-btn--outline",
            onClick: k,
            disabled: !!i.value
          }, [
            g("span", {
              innerHTML: p(K).sheet
            }, null, 8, qu),
            de(" " + D(i.value === "excel" ? "Exportiere..." : "Excel-Export"), 1)
          ], 8, Ku)) : V("", !0),
          u.value ? (m(), v("button", {
            key: 2,
            class: "pxc-btn pxc-btn--outline",
            onClick: F
          }, [
            g("span", {
              innerHTML: p(K).compare
            }, null, 8, Gu),
            de(" Vergleichen (" + D(p(n).wishlistCount.value) + ") ", 1)
          ])) : V("", !0),
          p(t).settings.catalog_share_wishlist_enabled ? (m(), v("button", {
            key: 3,
            class: "pxc-btn pxc-btn--ghost",
            onClick: E
          }, [
            g("span", {
              innerHTML: o.value ? p(K).check : p(K).share
            }, null, 8, Ju),
            de(" " + D(o.value ? "Link kopiert!" : "Teilen"), 1)
          ])) : V("", !0),
          g("button", {
            class: "pxc-btn pxc-btn--danger",
            onClick: P[0] || (P[0] = (T) => p(s).clearWishlist())
          }, [
            g("span", {
              innerHTML: p(K).trash
            }, null, 8, zu),
            P[4] || (P[4] = de(" Leeren ", -1))
          ])
        ])) : V("", !0)
      ], 2)
    ]));
  }
}, Yu = ["innerHTML"], Qu = {
  key: 0,
  class: "pxc-wishlist-btn__badge"
}, Xu = {
  __name: "WishlistButtonWidget",
  setup(e) {
    const { state: t, getters: s } = Pe();
    function n() {
      window.dispatchEvent(new CustomEvent("pxc:open-wishlist"));
    }
    return (r, i) => (m(), v("button", {
      class: "pxc-wishlist-btn",
      onClick: n
    }, [
      g("span", {
        innerHTML: p(K).heart
      }, null, 8, Yu),
      i[0] || (i[0] = g("span", null, "Merkliste", -1)),
      p(s).wishlistCount.value > 0 ? (m(), v("span", Qu, D(p(s).wishlistCount.value), 1)) : V("", !0)
    ]));
  }
}, ef = { class: "pxc-detail-modal" }, tf = ["innerHTML"], sf = {
  key: 0,
  class: "pxc-detail-modal__loading"
}, nf = ["innerHTML"], rf = {
  key: 1,
  class: "pxc-detail"
}, of = { class: "pxc-detail__layout" }, lf = { class: "pxc-detail__gallery" }, cf = { class: "pxc-detail__main-image" }, af = ["src", "alt"], uf = {
  key: 1,
  class: "pxc-detail__no-image"
}, ff = ["innerHTML"], df = ["innerHTML"], hf = ["innerHTML"], pf = {
  key: 0,
  class: "pxc-detail__thumbs"
}, gf = ["onClick"], _f = ["src", "alt"], mf = { class: "pxc-detail__info" }, vf = {
  key: 0,
  class: "pxc-detail__breadcrumb"
}, yf = { class: "pxc-detail__title" }, xf = { class: "pxc-detail__meta" }, bf = { key: 0 }, wf = { key: 1 }, Cf = {
  key: 1,
  class: "pxc-detail__prices"
}, kf = { class: "pxc-detail__price-label" }, Tf = { class: "pxc-detail__price-value" }, $f = { class: "pxc-detail__actions" }, Sf = ["innerHTML"], Mf = ["innerHTML"], Pf = {
  key: 2,
  class: "pxc-detail__sections"
}, Lf = { class: "pxc-detail__section-title" }, Af = { class: "pxc-detail__table" }, Ef = { class: "pxc-detail__table-label" }, If = { class: "pxc-detail__table-value" }, Hf = ["href"], Ff = {
  key: 2,
  class: "pxc-text-muted"
}, Of = {
  key: 3,
  class: "pxc-detail__relations"
}, Df = { class: "pxc-detail__section-title" }, Rf = { class: "pxc-detail__relation-items" }, jf = ["onClick"], Nf = ["src", "alt"], Vf = ["innerHTML"], Wf = {
  key: 2,
  class: "pxc-detail-modal__error"
}, Bf = {
  __name: "ProductDetailWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Pe(), r = /* @__PURE__ */ Ze(0), i = Me(() => {
      var d;
      return (d = t.currentProduct) != null && d.media ? t.currentProduct.media.filter((_) => _.media_type === "image") : [];
    }), o = Me(() => i.value[r.value]);
    Ye(() => t.currentProduct, () => {
      r.value = 0;
    });
    function l() {
      r.value = r.value > 0 ? r.value - 1 : i.value.length - 1;
    }
    function c() {
      r.value = r.value < i.value.length - 1 ? r.value + 1 : 0;
    }
    function u(d, _) {
      return d ? new Intl.NumberFormat(t.locale === "de" ? "de-DE" : "en-US", {
        style: "currency",
        currency: _ || "EUR"
      }).format(d) : null;
    }
    async function a() {
      t.currentProduct && await s.downloadProductPdf(t.currentProduct.id);
    }
    return (d, _) => (m(), Un(xi, { to: "body" }, [
      ke(eo, { name: "pxc-fade" }, {
        default: Nn(() => {
          var k, F, E, j;
          return [
            p(t).detailOpen ? (m(), v("div", {
              key: 0,
              class: "pxc-detail-overlay",
              onClick: _[2] || (_[2] = no((P) => p(s).closeDetail(), ["self"]))
            }, [
              g("div", ef, [
                g("button", {
                  class: "pxc-detail-modal__close",
                  onClick: _[0] || (_[0] = (P) => p(s).closeDetail()),
                  innerHTML: p(K).x
                }, null, 8, tf),
                p(t).productLoading ? (m(), v("div", sf, [
                  g("span", {
                    innerHTML: p(K).loader,
                    style: { width: "32px", height: "32px" }
                  }, null, 8, nf),
                  _[3] || (_[3] = g("p", null, "Lade Produktdetails...", -1))
                ])) : p(t).currentProduct ? (m(), v("div", rf, [
                  g("div", of, [
                    g("div", lf, [
                      g("div", cf, [
                        o.value ? (m(), v("img", {
                          key: 0,
                          src: o.value.url,
                          alt: o.value.alt || ""
                        }, null, 8, af)) : (m(), v("div", uf, [
                          g("span", {
                            innerHTML: p(K).package,
                            style: { width: "64px", height: "64px", opacity: "0.1" }
                          }, null, 8, ff)
                        ])),
                        i.value.length > 1 ? (m(), v(J, { key: 2 }, [
                          g("button", {
                            class: "pxc-detail__nav pxc-detail__nav--prev",
                            onClick: l,
                            innerHTML: p(K).chevronLeft
                          }, null, 8, df),
                          g("button", {
                            class: "pxc-detail__nav pxc-detail__nav--next",
                            onClick: c,
                            innerHTML: p(K).chevronRight
                          }, null, 8, hf)
                        ], 64)) : V("", !0)
                      ]),
                      i.value.length > 1 ? (m(), v("div", pf, [
                        (m(!0), v(J, null, ue(i.value, (P, T) => (m(), v("button", {
                          key: P.url,
                          class: pe(["pxc-detail__thumb", { "pxc-detail__thumb--active": T === r.value }]),
                          onClick: (z) => r.value = T
                        }, [
                          g("img", {
                            src: P.url,
                            alt: P.alt || ""
                          }, null, 8, _f)
                        ], 10, gf))), 128))
                      ])) : V("", !0)
                    ]),
                    g("div", mf, [
                      (k = p(t).currentProduct.breadcrumbs) != null && k.length ? (m(), v("p", vf, [
                        (m(!0), v(J, null, ue(p(t).currentProduct.breadcrumbs, (P, T) => (m(), v("span", { key: T }, [
                          de(D(P.name), 1),
                          T < p(t).currentProduct.breadcrumbs.length - 1 ? (m(), v(J, { key: 0 }, [
                            de(" / ")
                          ], 64)) : V("", !0)
                        ]))), 128))
                      ])) : V("", !0),
                      g("h2", yf, D(p(t).currentProduct.name), 1),
                      g("div", xf, [
                        p(t).currentProduct.sku ? (m(), v("span", bf, "SKU: " + D(p(t).currentProduct.sku), 1)) : V("", !0),
                        p(t).currentProduct.ean ? (m(), v("span", wf, "EAN: " + D(p(t).currentProduct.ean), 1)) : V("", !0)
                      ]),
                      (F = p(t).currentProduct.prices) != null && F.length ? (m(), v("div", Cf, [
                        (m(!0), v(J, null, ue(p(t).currentProduct.prices, (P, T) => (m(), v("div", {
                          key: T,
                          class: "pxc-detail__price"
                        }, [
                          g("span", kf, D(P.type_name || "Preis"), 1),
                          g("span", Tf, D(u(P.value, P.currency)), 1)
                        ]))), 128))
                      ])) : V("", !0),
                      g("div", $f, [
                        g("button", {
                          class: pe(["pxc-btn", p(n).isInWishlist(p(t).currentProduct.id) ? "pxc-btn--accent" : "pxc-btn--outline"]),
                          onClick: _[1] || (_[1] = (P) => p(s).toggleWishlist(p(t).currentProduct.id))
                        }, [
                          g("span", {
                            innerHTML: p(n).isInWishlist(p(t).currentProduct.id) ? p(K).heartFilled : p(K).heart
                          }, null, 8, Sf),
                          de(" " + D(p(n).isInWishlist(p(t).currentProduct.id) ? "Auf Merkliste" : "Zur Merkliste"), 1)
                        ], 2),
                        p(t).settings.catalog_pdf_enabled ? (m(), v("button", {
                          key: 0,
                          class: "pxc-btn pxc-btn--outline",
                          onClick: a
                        }, [
                          g("span", {
                            innerHTML: p(K).fileDown
                          }, null, 8, Mf),
                          _[4] || (_[4] = de(" PDF ", -1))
                        ])) : V("", !0)
                      ]),
                      (E = p(t).currentProduct.attribute_sections) != null && E.length ? (m(), v("div", Pf, [
                        (m(!0), v(J, null, ue(p(t).currentProduct.attribute_sections, (P) => (m(), v("div", {
                          key: P.name,
                          class: "pxc-detail__section"
                        }, [
                          g("h3", Lf, D(P.name), 1),
                          g("table", Af, [
                            (m(!0), v(J, null, ue(P.attributes, (T) => (m(), v("tr", {
                              key: T.attribute_id
                            }, [
                              g("td", Ef, D(T.label), 1),
                              g("td", If, [
                                T.type === "Hyperlink" ? (m(), v("a", {
                                  key: 0,
                                  href: T.value,
                                  target: "_blank",
                                  rel: "noopener"
                                }, D(T.value), 9, Hf)) : (m(), v(J, { key: 1 }, [
                                  de(D(T.display_value || T.value || "—"), 1)
                                ], 64)),
                                T.unit ? (m(), v("span", Ff, D(T.unit), 1)) : V("", !0)
                              ])
                            ]))), 128))
                          ])
                        ]))), 128))
                      ])) : V("", !0),
                      (j = p(t).currentProduct.relations) != null && j.length ? (m(), v("div", Of, [
                        (m(!0), v(J, null, ue(p(t).currentProduct.relations, (P) => (m(), v("div", {
                          key: P.type_id,
                          class: "pxc-detail__relation-group"
                        }, [
                          g("h3", Df, D(P.type_name), 1),
                          g("div", Rf, [
                            (m(!0), v(J, null, ue(P.products, (T) => (m(), v("div", {
                              key: T.id,
                              class: "pxc-detail__relation-card",
                              onClick: (z) => p(s).openDetail(T.id)
                            }, [
                              T.image_url ? (m(), v("img", {
                                key: 0,
                                src: T.image_url,
                                alt: T.name
                              }, null, 8, Nf)) : (m(), v("span", {
                                key: 1,
                                innerHTML: p(K).package
                              }, null, 8, Vf)),
                              g("p", null, D(T.name), 1)
                            ], 8, jf))), 128))
                          ])
                        ]))), 128))
                      ])) : V("", !0)
                    ])
                  ])
                ])) : p(t).error ? (m(), v("div", Wf, [
                  g("p", null, D(p(t).error), 1)
                ])) : V("", !0)
              ])
            ])) : V("", !0)
          ];
        }),
        _: 1
      })
    ]));
  }
}, Uf = { class: "pxc-compare-modal" }, Kf = { class: "pxc-compare-modal__header" }, qf = ["innerHTML"], Gf = {
  key: 0,
  class: "pxc-text-muted"
}, Jf = { class: "pxc-compare-modal__filter" }, zf = ["innerHTML"], Zf = { class: "pxc-compare-modal__body" }, Yf = {
  key: 0,
  class: "pxc-compare-modal__loading"
}, Qf = {
  key: 1,
  class: "pxc-compare-table"
}, Xf = { class: "pxc-text-muted" }, ed = { key: 0 }, td = ["colspan"], sd = {
  __name: "CompareWidget",
  setup(e) {
    const { state: t, actions: s } = Pe(), n = /* @__PURE__ */ Ze(!1), r = Me(() => {
      var i;
      return (i = t.compareData) != null && i.rows ? n.value ? t.compareData.rows.filter((o) => o.is_different) : t.compareData.rows : [];
    });
    return (i, o) => (m(), Un(xi, { to: "body" }, [
      ke(eo, { name: "pxc-fade" }, {
        default: Nn(() => {
          var l, c;
          return [
            p(t).compareOpen ? (m(), v("div", {
              key: 0,
              class: "pxc-compare-overlay",
              onClick: o[2] || (o[2] = no((u) => p(s).closeCompare(), ["self"]))
            }, [
              g("div", Uf, [
                g("div", Kf, [
                  g("span", {
                    innerHTML: p(K).compare
                  }, null, 8, qf),
                  o[4] || (o[4] = g("span", null, "Produktvergleich", -1)),
                  p(t).compareData ? (m(), v("span", Gf, D(p(t).compareData.total_differences) + " Unterschiede von " + D(p(t).compareData.total_attributes) + " Feldern ", 1)) : V("", !0),
                  o[5] || (o[5] = g("div", { style: { flex: "1" } }, null, -1)),
                  g("label", Jf, [
                    hn(g("input", {
                      type: "checkbox",
                      "onUpdate:modelValue": o[0] || (o[0] = (u) => n.value = u)
                    }, null, 512), [
                      [Lc, n.value]
                    ]),
                    o[3] || (o[3] = de(" Nur Unterschiede ", -1))
                  ]),
                  g("button", {
                    class: "pxc-btn pxc-btn--ghost",
                    onClick: o[1] || (o[1] = (u) => p(s).closeCompare()),
                    innerHTML: p(K).x
                  }, null, 8, zf)
                ]),
                g("div", Zf, [
                  p(t).compareLoading ? (m(), v("div", Yf, [
                    (m(), v(J, null, ue(8, (u) => g("div", {
                      key: u,
                      class: "pxc-skeleton",
                      style: { height: "32px", "margin-bottom": "4px" }
                    })), 64))
                  ])) : p(t).compareData ? (m(), v("table", Qf, [
                    g("thead", null, [
                      g("tr", null, [
                        o[6] || (o[6] = g("th", null, "Attribut", -1)),
                        (m(!0), v(J, null, ue(p(t).compareData.products, (u) => (m(), v("th", {
                          key: u.id
                        }, [
                          de(D(u.sku) + " ", 1),
                          g("span", Xf, D(u.name), 1)
                        ]))), 128))
                      ])
                    ]),
                    g("tbody", null, [
                      (m(!0), v(J, null, ue(r.value, (u, a) => (m(), v("tr", {
                        key: a,
                        class: pe({ "pxc-compare-table__diff": u.is_different })
                      }, [
                        g("td", null, D(u.attribute_name), 1),
                        (m(!0), v(J, null, ue(u.values, (d, _) => (m(), v("td", { key: _ }, D(d ?? "—"), 1))), 128))
                      ], 2))), 128)),
                      r.value.length === 0 ? (m(), v("tr", ed, [
                        g("td", {
                          colspan: 1 + (((c = (l = p(t).compareData) == null ? void 0 : l.products) == null ? void 0 : c.length) || 0),
                          style: { "text-align": "center", padding: "32px" }
                        }, D(n.value ? "Keine Unterschiede" : "Keine Attribute"), 9, td)
                      ])) : V("", !0)
                    ])
                  ])) : V("", !0)
                ])
              ])
            ])) : V("", !0)
          ];
        }),
        _: 1
      })
    ]));
  }
}, nd = { class: "pxc-locale" }, rd = ["innerHTML"], id = {
  __name: "LocaleWidget",
  setup(e) {
    const { state: t, actions: s } = Pe();
    function n(r) {
      s.setLocale(r), s.fetchProducts(), s.fetchCategories();
    }
    return (r, i) => (m(), v("div", nd, [
      g("span", {
        innerHTML: p(K).globe
      }, null, 8, rd),
      g("button", {
        class: pe(["pxc-locale__btn", { "pxc-locale__btn--active": p(t).locale === "de" }]),
        onClick: i[0] || (i[0] = (o) => n("de"))
      }, "DE", 2),
      g("button", {
        class: pe(["pxc-locale__btn", { "pxc-locale__btn--active": p(t).locale === "en" }]),
        onClick: i[1] || (i[1] = (o) => n("en"))
      }, "EN", 2)
    ]));
  }
}, od = {
  key: 0,
  class: "pxc-active-filters"
}, ld = ["onClick", "innerHTML"], cd = {
  __name: "ActiveFiltersWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Pe(), r = Me(() => {
      const l = [];
      t.selectedCategoryName && l.push({ type: "category", label: t.selectedCategoryName }), t.search && l.push({ type: "search", label: `"${t.search}"` });
      for (const [c, u] of Object.entries(t.activeFilters)) {
        const a = t.facets.find((_) => String(_.attribute_id) === String(c)), d = a ? a.label : `Filter ${c}`;
        l.push({ type: "filter", attrId: c, label: `${d}: ${u}` });
      }
      return l;
    });
    function i(l) {
      l.type === "category" ? s.clearCategory() : l.type === "search" ? s.setSearch("") : l.type === "filter" && s.clearFilter(l.attrId), s.fetchProducts();
    }
    function o() {
      s.setSearch(""), s.clearCategory(), s.clearAllFilters(), s.fetchProducts();
    }
    return (l, c) => r.value.length > 0 ? (m(), v("div", od, [
      (m(!0), v(J, null, ue(r.value, (u, a) => (m(), v("span", {
        key: a,
        class: "pxc-active-filters__chip"
      }, [
        de(D(u.label) + " ", 1),
        g("button", {
          onClick: (d) => i(u),
          innerHTML: p(K).x
        }, null, 8, ld)
      ]))), 128)),
      r.value.length > 1 ? (m(), v("button", {
        key: 0,
        class: "pxc-active-filters__clear",
        onClick: o
      }, " Alle löschen ")) : V("", !0)
    ])) : V("", !0);
  }
}, Cn = {
  search: qc,
  categories: fa,
  facets: Ha,
  "product-grid": eu,
  pagination: uu,
  toolbar: bu,
  wishlist: Zu,
  "wishlist-button": Xu,
  "product-detail": Bf,
  compare: sd,
  locale: id,
  "active-filters": cd
}, kn = [];
function Tn() {
  document.querySelectorAll("[data-catalog]").forEach((t) => {
    if (t.__pxc_mounted) return;
    const s = t.getAttribute("data-catalog"), n = Cn[s];
    if (!n) {
      console.warn(`[PublixxCatalog] Unknown widget: "${s}". Available: ${Object.keys(Cn).join(", ")}`);
      return;
    }
    const r = {};
    for (const o of t.attributes)
      if (o.name.startsWith("data-") && o.name !== "data-catalog") {
        const l = o.name.slice(5).replace(/-([a-z])/g, (c, u) => u.toUpperCase());
        r[l] = o.value;
      }
    const i = Oc({
      render() {
        return Yi(n, r);
      }
    });
    i.mount(t), t.__pxc_mounted = !0, kn.push({ el: t, app: i });
  });
}
function ad() {
  kn.forEach(({ app: e }) => e.unmount()), kn.length = 0;
}
async function ud(e = {}) {
  jc({
    baseUrl: e.api || e.baseUrl || "/api/v1",
    token: e.token,
    timeout: e.timeout
  });
  const { state: t, actions: s } = Pe();
  e.locale && (t.locale = e.locale), e.perPage && (t.meta.per_page = e.perPage), await s.fetchSettings(), s.importWishlistFromUrl(), e.autoMount !== !1 && (document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", Tn) : Tn());
}
const fd = {
  init: ud,
  mount: Tn,
  destroy: ad,
  store: Pe,
  widgets: Cn,
  version: "1.0.0"
};
typeof window < "u" && (window.PublixxCatalog = fd);
export {
  fd as default,
  ad as destroy,
  ud as init,
  Tn as mount,
  Pe as useStore
};
