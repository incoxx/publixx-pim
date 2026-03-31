/**
* @vue/shared v3.5.31
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
// @__NO_SIDE_EFFECTS__
function Gs(e) {
  const t = /* @__PURE__ */ Object.create(null);
  for (const s of e.split(",")) t[s] = 1;
  return (s) => s in t;
}
const U = {}, ft = [], Ie = () => {
}, Qn = () => !1, ns = (e) => e.charCodeAt(0) === 111 && e.charCodeAt(1) === 110 && // uppercase letter
(e.charCodeAt(2) > 122 || e.charCodeAt(2) < 97), rs = (e) => e.startsWith("onUpdate:"), le = Object.assign, qs = (e, t) => {
  const s = e.indexOf(t);
  s > -1 && e.splice(s, 1);
}, ai = Object.prototype.hasOwnProperty, D = (e, t) => ai.call(e, t), P = Array.isArray, ut = (e) => Ft(e) === "[object Map]", er = (e) => Ft(e) === "[object Set]", bn = (e) => Ft(e) === "[object Date]", R = (e) => typeof e == "function", z = (e) => typeof e == "string", $e = (e) => typeof e == "symbol", H = (e) => e !== null && typeof e == "object", tr = (e) => (H(e) || R(e)) && R(e.then) && R(e.catch), sr = Object.prototype.toString, Ft = (e) => sr.call(e), di = (e) => Ft(e).slice(8, -1), nr = (e) => Ft(e) === "[object Object]", Js = (e) => z(e) && e !== "NaN" && e[0] !== "-" && "" + parseInt(e, 10) === e, St = /* @__PURE__ */ Gs(
  // the leading comma is intentional so empty string "" is also included
  ",key,ref,ref_for,ref_key,onVnodeBeforeMount,onVnodeMounted,onVnodeBeforeUpdate,onVnodeUpdated,onVnodeBeforeUnmount,onVnodeUnmounted"
), is = (e) => {
  const t = /* @__PURE__ */ Object.create(null);
  return ((s) => t[s] || (t[s] = e(s)));
}, hi = /-\w/g, ae = is(
  (e) => e.replace(hi, (t) => t.slice(1).toUpperCase())
), pi = /\B([A-Z])/g, rt = is(
  (e) => e.replace(pi, "-$1").toLowerCase()
), ls = is((e) => e.charAt(0).toUpperCase() + e.slice(1)), ms = is(
  (e) => e ? `on${ls(e)}` : ""
), Me = (e, t) => !Object.is(e, t), Kt = (e, ...t) => {
  for (let s = 0; s < e.length; s++)
    e[s](...t);
}, rr = (e, t, s, n = !1) => {
  Object.defineProperty(e, t, {
    configurable: !0,
    enumerable: !1,
    writable: n,
    value: s
  });
}, zs = (e) => {
  const t = parseFloat(e);
  return isNaN(t) ? e : t;
};
let vn;
const os = () => vn || (vn = typeof globalThis < "u" ? globalThis : typeof self < "u" ? self : typeof window < "u" ? window : typeof global < "u" ? global : {});
function Ys(e) {
  if (P(e)) {
    const t = {};
    for (let s = 0; s < e.length; s++) {
      const n = e[s], r = z(n) ? bi(n) : Ys(n);
      if (r)
        for (const i in r)
          t[i] = r[i];
    }
    return t;
  } else if (z(e) || H(e))
    return e;
}
const gi = /;(?![^(]*\))/g, _i = /:([^]+)/, mi = /\/\*[^]*?\*\//g;
function bi(e) {
  const t = {};
  return e.replace(mi, "").split(gi).forEach((s) => {
    if (s) {
      const n = s.split(_i);
      n.length > 1 && (t[n[0].trim()] = n[1].trim());
    }
  }), t;
}
function it(e) {
  let t = "";
  if (z(e))
    t = e;
  else if (P(e))
    for (let s = 0; s < e.length; s++) {
      const n = it(e[s]);
      n && (t += n + " ");
    }
  else if (H(e))
    for (const s in e)
      e[s] && (t += s + " ");
  return t.trim();
}
const vi = "itemscope,allowfullscreen,formnovalidate,ismap,nomodule,novalidate,readonly", yi = /* @__PURE__ */ Gs(vi);
function ir(e) {
  return !!e || e === "";
}
function xi(e, t) {
  if (e.length !== t.length) return !1;
  let s = !0;
  for (let n = 0; s && n < e.length; n++)
    s = Xs(e[n], t[n]);
  return s;
}
function Xs(e, t) {
  if (e === t) return !0;
  let s = bn(e), n = bn(t);
  if (s || n)
    return s && n ? e.getTime() === t.getTime() : !1;
  if (s = $e(e), n = $e(t), s || n)
    return e === t;
  if (s = P(e), n = P(t), s || n)
    return s && n ? xi(e, t) : !1;
  if (s = H(e), n = H(t), s || n) {
    if (!s || !n)
      return !1;
    const r = Object.keys(e).length, i = Object.keys(t).length;
    if (r !== i)
      return !1;
    for (const l in e) {
      const o = e.hasOwnProperty(l), c = t.hasOwnProperty(l);
      if (o && !c || !o && c || !Xs(e[l], t[l]))
        return !1;
    }
  }
  return String(e) === String(t);
}
const lr = (e) => !!(e && e.__v_isRef === !0), ee = (e) => z(e) ? e : e == null ? "" : P(e) || H(e) && (e.toString === sr || !R(e.toString)) ? lr(e) ? ee(e.value) : JSON.stringify(e, or, 2) : String(e), or = (e, t) => lr(t) ? or(e, t.value) : ut(t) ? {
  [`Map(${t.size})`]: [...t.entries()].reduce(
    (s, [n, r], i) => (s[bs(n, i) + " =>"] = r, s),
    {}
  )
} : er(t) ? {
  [`Set(${t.size})`]: [...t.values()].map((s) => bs(s))
} : $e(t) ? bs(t) : H(t) && !P(t) && !nr(t) ? String(t) : t, bs = (e, t = "") => {
  var s;
  return (
    // Symbol.description in es2019+ so we need to cast here to pass
    // the lib: es2016 check
    $e(e) ? `Symbol(${(s = e.description) != null ? s : t})` : e
  );
};
/**
* @vue/reactivity v3.5.31
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
let he;
class Si {
  // TODO isolatedDeclarations "__v_skip"
  constructor(t = !1) {
    this.detached = t, this._active = !0, this._on = 0, this.effects = [], this.cleanups = [], this._isPaused = !1, this.__v_skip = !0, this.parent = he, !t && he && (this.index = (he.scopes || (he.scopes = [])).push(
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
      const s = he;
      try {
        return he = this, t();
      } finally {
        he = s;
      }
    }
  }
  /**
   * This should only be called on non-detached scopes
   * @internal
   */
  on() {
    ++this._on === 1 && (this.prevScope = he, he = this);
  }
  /**
   * This should only be called on non-detached scopes
   * @internal
   */
  off() {
    this._on > 0 && --this._on === 0 && (he = this.prevScope, this.prevScope = void 0);
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
function wi() {
  return he;
}
let K;
const vs = /* @__PURE__ */ new WeakSet();
class cr {
  constructor(t) {
    this.fn = t, this.deps = void 0, this.depsTail = void 0, this.flags = 5, this.next = void 0, this.cleanup = void 0, this.scheduler = void 0, he && he.active && he.effects.push(this);
  }
  pause() {
    this.flags |= 64;
  }
  resume() {
    this.flags & 64 && (this.flags &= -65, vs.has(this) && (vs.delete(this), this.trigger()));
  }
  /**
   * @internal
   */
  notify() {
    this.flags & 2 && !(this.flags & 32) || this.flags & 8 || ur(this);
  }
  run() {
    if (!(this.flags & 1))
      return this.fn();
    this.flags |= 2, yn(this), ar(this);
    const t = K, s = ve;
    K = this, ve = !0;
    try {
      return this.fn();
    } finally {
      dr(this), K = t, ve = s, this.flags &= -3;
    }
  }
  stop() {
    if (this.flags & 1) {
      for (let t = this.deps; t; t = t.nextDep)
        en(t);
      this.deps = this.depsTail = void 0, yn(this), this.onStop && this.onStop(), this.flags &= -2;
    }
  }
  trigger() {
    this.flags & 64 ? vs.add(this) : this.scheduler ? this.scheduler() : this.runIfDirty();
  }
  /**
   * @internal
   */
  runIfDirty() {
    Ms(this) && this.run();
  }
  get dirty() {
    return Ms(this);
  }
}
let fr = 0, wt, Ct;
function ur(e, t = !1) {
  if (e.flags |= 8, t) {
    e.next = Ct, Ct = e;
    return;
  }
  e.next = wt, wt = e;
}
function Zs() {
  fr++;
}
function Qs() {
  if (--fr > 0)
    return;
  if (Ct) {
    let t = Ct;
    for (Ct = void 0; t; ) {
      const s = t.next;
      t.next = void 0, t.flags &= -9, t = s;
    }
  }
  let e;
  for (; wt; ) {
    let t = wt;
    for (wt = void 0; t; ) {
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
function ar(e) {
  for (let t = e.deps; t; t = t.nextDep)
    t.version = -1, t.prevActiveLink = t.dep.activeLink, t.dep.activeLink = t;
}
function dr(e) {
  let t, s = e.depsTail, n = s;
  for (; n; ) {
    const r = n.prevDep;
    n.version === -1 ? (n === s && (s = r), en(n), Ci(n)) : t = n, n.dep.activeLink = n.prevActiveLink, n.prevActiveLink = void 0, n = r;
  }
  e.deps = t, e.depsTail = s;
}
function Ms(e) {
  for (let t = e.deps; t; t = t.nextDep)
    if (t.dep.version !== t.version || t.dep.computed && (hr(t.dep.computed) || t.dep.version !== t.version))
      return !0;
  return !!e._dirty;
}
function hr(e) {
  if (e.flags & 4 && !(e.flags & 16) || (e.flags &= -17, e.globalVersion === Ot) || (e.globalVersion = Ot, !e.isSSR && e.flags & 128 && (!e.deps && !e._dirty || !Ms(e))))
    return;
  e.flags |= 2;
  const t = e.dep, s = K, n = ve;
  K = e, ve = !0;
  try {
    ar(e);
    const r = e.fn(e._value);
    (t.version === 0 || Me(r, e._value)) && (e.flags |= 128, e._value = r, t.version++);
  } catch (r) {
    throw t.version++, r;
  } finally {
    K = s, ve = n, dr(e), e.flags &= -3;
  }
}
function en(e, t = !1) {
  const { dep: s, prevSub: n, nextSub: r } = e;
  if (n && (n.nextSub = r, e.prevSub = void 0), r && (r.prevSub = n, e.nextSub = void 0), s.subs === e && (s.subs = n, !n && s.computed)) {
    s.computed.flags &= -5;
    for (let i = s.computed.deps; i; i = i.nextDep)
      en(i, !0);
  }
  !t && !--s.sc && s.map && s.map.delete(s.key);
}
function Ci(e) {
  const { prevDep: t, nextDep: s } = e;
  t && (t.nextDep = s, e.prevDep = void 0), s && (s.prevDep = t, e.nextDep = void 0);
}
let ve = !0;
const pr = [];
function Le() {
  pr.push(ve), ve = !1;
}
function Ve() {
  const e = pr.pop();
  ve = e === void 0 ? !0 : e;
}
function yn(e) {
  const { cleanup: t } = e;
  if (e.cleanup = void 0, t) {
    const s = K;
    K = void 0;
    try {
      t();
    } finally {
      K = s;
    }
  }
}
let Ot = 0;
class Ti {
  constructor(t, s) {
    this.sub = t, this.dep = s, this.version = s.version, this.nextDep = this.prevDep = this.nextSub = this.prevSub = this.prevActiveLink = void 0;
  }
}
class tn {
  // TODO isolatedDeclarations "__v_skip"
  constructor(t) {
    this.computed = t, this.version = 0, this.activeLink = void 0, this.subs = void 0, this.map = void 0, this.key = void 0, this.sc = 0, this.__v_skip = !0;
  }
  track(t) {
    if (!K || !ve || K === this.computed)
      return;
    let s = this.activeLink;
    if (s === void 0 || s.sub !== K)
      s = this.activeLink = new Ti(K, this), K.deps ? (s.prevDep = K.depsTail, K.depsTail.nextDep = s, K.depsTail = s) : K.deps = K.depsTail = s, gr(s);
    else if (s.version === -1 && (s.version = this.version, s.nextDep)) {
      const n = s.nextDep;
      n.prevDep = s.prevDep, s.prevDep && (s.prevDep.nextDep = n), s.prevDep = K.depsTail, s.nextDep = void 0, K.depsTail.nextDep = s, K.depsTail = s, K.deps === s && (K.deps = n);
    }
    return s;
  }
  trigger(t) {
    this.version++, Ot++, this.notify(t);
  }
  notify(t) {
    Zs();
    try {
      for (let s = this.subs; s; s = s.prevSub)
        s.sub.notify() && s.sub.dep.notify();
    } finally {
      Qs();
    }
  }
}
function gr(e) {
  if (e.dep.sc++, e.sub.flags & 4) {
    const t = e.dep.computed;
    if (t && !e.dep.subs) {
      t.flags |= 20;
      for (let n = t.deps; n; n = n.nextDep)
        gr(n);
    }
    const s = e.dep.subs;
    s !== e && (e.prevSub = s, s && (s.nextSub = e)), e.dep.subs = e;
  }
}
const Rs = /* @__PURE__ */ new WeakMap(), tt = /* @__PURE__ */ Symbol(
  ""
), Is = /* @__PURE__ */ Symbol(
  ""
), Mt = /* @__PURE__ */ Symbol(
  ""
);
function se(e, t, s) {
  if (ve && K) {
    let n = Rs.get(e);
    n || Rs.set(e, n = /* @__PURE__ */ new Map());
    let r = n.get(s);
    r || (n.set(s, r = new tn()), r.map = n, r.key = s), r.track();
  }
}
function He(e, t, s, n, r, i) {
  const l = Rs.get(e);
  if (!l) {
    Ot++;
    return;
  }
  const o = (c) => {
    c && c.trigger();
  };
  if (Zs(), t === "clear")
    l.forEach(o);
  else {
    const c = P(e), d = c && Js(s);
    if (c && s === "length") {
      const u = Number(n);
      l.forEach((p, w) => {
        (w === "length" || w === Mt || !$e(w) && w >= u) && o(p);
      });
    } else
      switch ((s !== void 0 || l.has(void 0)) && o(l.get(s)), d && o(l.get(Mt)), t) {
        case "add":
          c ? d && o(l.get("length")) : (o(l.get(tt)), ut(e) && o(l.get(Is)));
          break;
        case "delete":
          c || (o(l.get(tt)), ut(e) && o(l.get(Is)));
          break;
        case "set":
          ut(e) && o(l.get(tt));
          break;
      }
  }
  Qs();
}
function lt(e) {
  const t = /* @__PURE__ */ F(e);
  return t === e ? t : (se(t, "iterate", Mt), /* @__PURE__ */ be(e) ? t : t.map(ye));
}
function cs(e) {
  return se(e = /* @__PURE__ */ F(e), "iterate", Mt), e;
}
function Pe(e, t) {
  return /* @__PURE__ */ Ue(e) ? ht(/* @__PURE__ */ st(e) ? ye(t) : t) : ye(t);
}
const Ei = {
  __proto__: null,
  [Symbol.iterator]() {
    return ys(this, Symbol.iterator, (e) => Pe(this, e));
  },
  concat(...e) {
    return lt(this).concat(
      ...e.map((t) => P(t) ? lt(t) : t)
    );
  },
  entries() {
    return ys(this, "entries", (e) => (e[1] = Pe(this, e[1]), e));
  },
  every(e, t) {
    return De(this, "every", e, t, void 0, arguments);
  },
  filter(e, t) {
    return De(
      this,
      "filter",
      e,
      t,
      (s) => s.map((n) => Pe(this, n)),
      arguments
    );
  },
  find(e, t) {
    return De(
      this,
      "find",
      e,
      t,
      (s) => Pe(this, s),
      arguments
    );
  },
  findIndex(e, t) {
    return De(this, "findIndex", e, t, void 0, arguments);
  },
  findLast(e, t) {
    return De(
      this,
      "findLast",
      e,
      t,
      (s) => Pe(this, s),
      arguments
    );
  },
  findLastIndex(e, t) {
    return De(this, "findLastIndex", e, t, void 0, arguments);
  },
  // flat, flatMap could benefit from ARRAY_ITERATE but are not straight-forward to implement
  forEach(e, t) {
    return De(this, "forEach", e, t, void 0, arguments);
  },
  includes(...e) {
    return xs(this, "includes", e);
  },
  indexOf(...e) {
    return xs(this, "indexOf", e);
  },
  join(e) {
    return lt(this).join(e);
  },
  // keys() iterator only reads `length`, no optimization required
  lastIndexOf(...e) {
    return xs(this, "lastIndexOf", e);
  },
  map(e, t) {
    return De(this, "map", e, t, void 0, arguments);
  },
  pop() {
    return vt(this, "pop");
  },
  push(...e) {
    return vt(this, "push", e);
  },
  reduce(e, ...t) {
    return xn(this, "reduce", e, t);
  },
  reduceRight(e, ...t) {
    return xn(this, "reduceRight", e, t);
  },
  shift() {
    return vt(this, "shift");
  },
  // slice could use ARRAY_ITERATE but also seems to beg for range tracking
  some(e, t) {
    return De(this, "some", e, t, void 0, arguments);
  },
  splice(...e) {
    return vt(this, "splice", e);
  },
  toReversed() {
    return lt(this).toReversed();
  },
  toSorted(e) {
    return lt(this).toSorted(e);
  },
  toSpliced(...e) {
    return lt(this).toSpliced(...e);
  },
  unshift(...e) {
    return vt(this, "unshift", e);
  },
  values() {
    return ys(this, "values", (e) => Pe(this, e));
  }
};
function ys(e, t, s) {
  const n = cs(e), r = n[t]();
  return n !== e && !/* @__PURE__ */ be(e) && (r._next = r.next, r.next = () => {
    const i = r._next();
    return i.done || (i.value = s(i.value)), i;
  }), r;
}
const Ai = Array.prototype;
function De(e, t, s, n, r, i) {
  const l = cs(e), o = l !== e && !/* @__PURE__ */ be(e), c = l[t];
  if (c !== Ai[t]) {
    const p = c.apply(e, i);
    return o ? ye(p) : p;
  }
  let d = s;
  l !== e && (o ? d = function(p, w) {
    return s.call(this, Pe(e, p), w, e);
  } : s.length > 2 && (d = function(p, w) {
    return s.call(this, p, w, e);
  }));
  const u = c.call(l, d, n);
  return o && r ? r(u) : u;
}
function xn(e, t, s, n) {
  const r = cs(e), i = r !== e && !/* @__PURE__ */ be(e);
  let l = s, o = !1;
  r !== e && (i ? (o = n.length === 0, l = function(d, u, p) {
    return o && (o = !1, d = Pe(e, d)), s.call(this, d, Pe(e, u), p, e);
  }) : s.length > 3 && (l = function(d, u, p) {
    return s.call(this, d, u, p, e);
  }));
  const c = r[t](l, ...n);
  return o ? Pe(e, c) : c;
}
function xs(e, t, s) {
  const n = /* @__PURE__ */ F(e);
  se(n, "iterate", Mt);
  const r = n[t](...s);
  return (r === -1 || r === !1) && /* @__PURE__ */ rn(s[0]) ? (s[0] = /* @__PURE__ */ F(s[0]), n[t](...s)) : r;
}
function vt(e, t, s = []) {
  Le(), Zs();
  const n = (/* @__PURE__ */ F(e))[t].apply(e, s);
  return Qs(), Ve(), n;
}
const Pi = /* @__PURE__ */ Gs("__proto__,__v_isRef,__isVue"), _r = new Set(
  /* @__PURE__ */ Object.getOwnPropertyNames(Symbol).filter((e) => e !== "arguments" && e !== "caller").map((e) => Symbol[e]).filter($e)
);
function Oi(e) {
  $e(e) || (e = String(e));
  const t = /* @__PURE__ */ F(this);
  return se(t, "has", e), t.hasOwnProperty(e);
}
class mr {
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
      return n === (r ? i ? ki : xr : i ? yr : vr).get(t) || // receiver is not the reactive proxy, but has the same prototype
      // this means the receiver is a user proxy of the reactive proxy
      Object.getPrototypeOf(t) === Object.getPrototypeOf(n) ? t : void 0;
    const l = P(t);
    if (!r) {
      let c;
      if (l && (c = Ei[s]))
        return c;
      if (s === "hasOwnProperty")
        return Oi;
    }
    const o = Reflect.get(
      t,
      s,
      // if this is a proxy wrapping a ref, return methods using the raw ref
      // as receiver so that we don't have to call `toRaw` on the ref in all
      // its class methods
      /* @__PURE__ */ ie(t) ? t : n
    );
    if (($e(s) ? _r.has(s) : Pi(s)) || (r || se(t, "get", s), i))
      return o;
    if (/* @__PURE__ */ ie(o)) {
      const c = l && Js(s) ? o : o.value;
      return r && H(c) ? /* @__PURE__ */ Fs(c) : c;
    }
    return H(o) ? r ? /* @__PURE__ */ Fs(o) : /* @__PURE__ */ fs(o) : o;
  }
}
class br extends mr {
  constructor(t = !1) {
    super(!1, t);
  }
  set(t, s, n, r) {
    let i = t[s];
    const l = P(t) && Js(s);
    if (!this._isShallow) {
      const d = /* @__PURE__ */ Ue(i);
      if (!/* @__PURE__ */ be(n) && !/* @__PURE__ */ Ue(n) && (i = /* @__PURE__ */ F(i), n = /* @__PURE__ */ F(n)), !l && /* @__PURE__ */ ie(i) && !/* @__PURE__ */ ie(n))
        return d || (i.value = n), !0;
    }
    const o = l ? Number(s) < t.length : D(t, s), c = Reflect.set(
      t,
      s,
      n,
      /* @__PURE__ */ ie(t) ? t : r
    );
    return t === /* @__PURE__ */ F(r) && (o ? Me(n, i) && He(t, "set", s, n) : He(t, "add", s, n)), c;
  }
  deleteProperty(t, s) {
    const n = D(t, s);
    t[s];
    const r = Reflect.deleteProperty(t, s);
    return r && n && He(t, "delete", s, void 0), r;
  }
  has(t, s) {
    const n = Reflect.has(t, s);
    return (!$e(s) || !_r.has(s)) && se(t, "has", s), n;
  }
  ownKeys(t) {
    return se(
      t,
      "iterate",
      P(t) ? "length" : tt
    ), Reflect.ownKeys(t);
  }
}
class Mi extends mr {
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
const Ri = /* @__PURE__ */ new br(), Ii = /* @__PURE__ */ new Mi(), $i = /* @__PURE__ */ new br(!0);
const $s = (e) => e, Ut = (e) => Reflect.getPrototypeOf(e);
function Fi(e, t, s) {
  return function(...n) {
    const r = this.__v_raw, i = /* @__PURE__ */ F(r), l = ut(i), o = e === "entries" || e === Symbol.iterator && l, c = e === "keys" && l, d = r[e](...n), u = s ? $s : t ? ht : ye;
    return !t && se(
      i,
      "iterate",
      c ? Is : tt
    ), le(
      // inheriting all iterator properties
      Object.create(d),
      {
        // iterator protocol
        next() {
          const { value: p, done: w } = d.next();
          return w ? { value: p, done: w } : {
            value: o ? [u(p[0]), u(p[1])] : u(p),
            done: w
          };
        }
      }
    );
  };
}
function Wt(e) {
  return function(...t) {
    return e === "delete" ? !1 : e === "clear" ? void 0 : this;
  };
}
function Di(e, t) {
  const s = {
    get(r) {
      const i = this.__v_raw, l = /* @__PURE__ */ F(i), o = /* @__PURE__ */ F(r);
      e || (Me(r, o) && se(l, "get", r), se(l, "get", o));
      const { has: c } = Ut(l), d = t ? $s : e ? ht : ye;
      if (c.call(l, r))
        return d(i.get(r));
      if (c.call(l, o))
        return d(i.get(o));
      i !== l && i.get(r);
    },
    get size() {
      const r = this.__v_raw;
      return !e && se(/* @__PURE__ */ F(r), "iterate", tt), r.size;
    },
    has(r) {
      const i = this.__v_raw, l = /* @__PURE__ */ F(i), o = /* @__PURE__ */ F(r);
      return e || (Me(r, o) && se(l, "has", r), se(l, "has", o)), r === o ? i.has(r) : i.has(r) || i.has(o);
    },
    forEach(r, i) {
      const l = this, o = l.__v_raw, c = /* @__PURE__ */ F(o), d = t ? $s : e ? ht : ye;
      return !e && se(c, "iterate", tt), o.forEach((u, p) => r.call(i, d(u), d(p), l));
    }
  };
  return le(
    s,
    e ? {
      add: Wt("add"),
      set: Wt("set"),
      delete: Wt("delete"),
      clear: Wt("clear")
    } : {
      add(r) {
        const i = /* @__PURE__ */ F(this), l = Ut(i), o = /* @__PURE__ */ F(r), c = !t && !/* @__PURE__ */ be(r) && !/* @__PURE__ */ Ue(r) ? o : r;
        return l.has.call(i, c) || Me(r, c) && l.has.call(i, r) || Me(o, c) && l.has.call(i, o) || (i.add(c), He(i, "add", c, c)), this;
      },
      set(r, i) {
        !t && !/* @__PURE__ */ be(i) && !/* @__PURE__ */ Ue(i) && (i = /* @__PURE__ */ F(i));
        const l = /* @__PURE__ */ F(this), { has: o, get: c } = Ut(l);
        let d = o.call(l, r);
        d || (r = /* @__PURE__ */ F(r), d = o.call(l, r));
        const u = c.call(l, r);
        return l.set(r, i), d ? Me(i, u) && He(l, "set", r, i) : He(l, "add", r, i), this;
      },
      delete(r) {
        const i = /* @__PURE__ */ F(this), { has: l, get: o } = Ut(i);
        let c = l.call(i, r);
        c || (r = /* @__PURE__ */ F(r), c = l.call(i, r)), o && o.call(i, r);
        const d = i.delete(r);
        return c && He(i, "delete", r, void 0), d;
      },
      clear() {
        const r = /* @__PURE__ */ F(this), i = r.size !== 0, l = r.clear();
        return i && He(
          r,
          "clear",
          void 0,
          void 0
        ), l;
      }
    }
  ), [
    "keys",
    "values",
    "entries",
    Symbol.iterator
  ].forEach((r) => {
    s[r] = Fi(r, e, t);
  }), s;
}
function sn(e, t) {
  const s = Di(e, t);
  return (n, r, i) => r === "__v_isReactive" ? !e : r === "__v_isReadonly" ? e : r === "__v_raw" ? n : Reflect.get(
    D(s, r) && r in n ? s : n,
    r,
    i
  );
}
const Ni = {
  get: /* @__PURE__ */ sn(!1, !1)
}, ji = {
  get: /* @__PURE__ */ sn(!1, !0)
}, Hi = {
  get: /* @__PURE__ */ sn(!0, !1)
};
const vr = /* @__PURE__ */ new WeakMap(), yr = /* @__PURE__ */ new WeakMap(), xr = /* @__PURE__ */ new WeakMap(), ki = /* @__PURE__ */ new WeakMap();
function Li(e) {
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
function Vi(e) {
  return e.__v_skip || !Object.isExtensible(e) ? 0 : Li(di(e));
}
// @__NO_SIDE_EFFECTS__
function fs(e) {
  return /* @__PURE__ */ Ue(e) ? e : nn(
    e,
    !1,
    Ri,
    Ni,
    vr
  );
}
// @__NO_SIDE_EFFECTS__
function Ui(e) {
  return nn(
    e,
    !1,
    $i,
    ji,
    yr
  );
}
// @__NO_SIDE_EFFECTS__
function Fs(e) {
  return nn(
    e,
    !0,
    Ii,
    Hi,
    xr
  );
}
function nn(e, t, s, n, r) {
  if (!H(e) || e.__v_raw && !(t && e.__v_isReactive))
    return e;
  const i = Vi(e);
  if (i === 0)
    return e;
  const l = r.get(e);
  if (l)
    return l;
  const o = new Proxy(
    e,
    i === 2 ? n : s
  );
  return r.set(e, o), o;
}
// @__NO_SIDE_EFFECTS__
function st(e) {
  return /* @__PURE__ */ Ue(e) ? /* @__PURE__ */ st(e.__v_raw) : !!(e && e.__v_isReactive);
}
// @__NO_SIDE_EFFECTS__
function Ue(e) {
  return !!(e && e.__v_isReadonly);
}
// @__NO_SIDE_EFFECTS__
function be(e) {
  return !!(e && e.__v_isShallow);
}
// @__NO_SIDE_EFFECTS__
function rn(e) {
  return e ? !!e.__v_raw : !1;
}
// @__NO_SIDE_EFFECTS__
function F(e) {
  const t = e && e.__v_raw;
  return t ? /* @__PURE__ */ F(t) : e;
}
function Wi(e) {
  return !D(e, "__v_skip") && Object.isExtensible(e) && rr(e, "__v_skip", !0), e;
}
const ye = (e) => H(e) ? /* @__PURE__ */ fs(e) : e, ht = (e) => H(e) ? /* @__PURE__ */ Fs(e) : e;
// @__NO_SIDE_EFFECTS__
function ie(e) {
  return e ? e.__v_isRef === !0 : !1;
}
// @__NO_SIDE_EFFECTS__
function Bi(e) {
  return Ki(e, !1);
}
function Ki(e, t) {
  return /* @__PURE__ */ ie(e) ? e : new Gi(e, t);
}
class Gi {
  constructor(t, s) {
    this.dep = new tn(), this.__v_isRef = !0, this.__v_isShallow = !1, this._rawValue = s ? t : /* @__PURE__ */ F(t), this._value = s ? t : ye(t), this.__v_isShallow = s;
  }
  get value() {
    return this.dep.track(), this._value;
  }
  set value(t) {
    const s = this._rawValue, n = this.__v_isShallow || /* @__PURE__ */ be(t) || /* @__PURE__ */ Ue(t);
    t = n ? t : /* @__PURE__ */ F(t), Me(t, s) && (this._rawValue = t, this._value = n ? t : ye(t), this.dep.trigger());
  }
}
function Q(e) {
  return /* @__PURE__ */ ie(e) ? e.value : e;
}
const qi = {
  get: (e, t, s) => t === "__v_raw" ? e : Q(Reflect.get(e, t, s)),
  set: (e, t, s, n) => {
    const r = e[t];
    return /* @__PURE__ */ ie(r) && !/* @__PURE__ */ ie(s) ? (r.value = s, !0) : Reflect.set(e, t, s, n);
  }
};
function Sr(e) {
  return /* @__PURE__ */ st(e) ? e : new Proxy(e, qi);
}
class Ji {
  constructor(t, s, n) {
    this.fn = t, this.setter = s, this._value = void 0, this.dep = new tn(this), this.__v_isRef = !0, this.deps = void 0, this.depsTail = void 0, this.flags = 16, this.globalVersion = Ot - 1, this.next = void 0, this.effect = this, this.__v_isReadonly = !s, this.isSSR = n;
  }
  /**
   * @internal
   */
  notify() {
    if (this.flags |= 16, !(this.flags & 8) && // avoid infinite self recursion
    K !== this)
      return ur(this, !0), !0;
  }
  get value() {
    const t = this.dep.track();
    return hr(this), t && (t.version = this.dep.version), this._value;
  }
  set value(t) {
    this.setter && this.setter(t);
  }
}
// @__NO_SIDE_EFFECTS__
function zi(e, t, s = !1) {
  let n, r;
  return R(e) ? n = e : (n = e.get, r = e.set), new Ji(n, r, s);
}
const Bt = {}, zt = /* @__PURE__ */ new WeakMap();
let et;
function Yi(e, t = !1, s = et) {
  if (s) {
    let n = zt.get(s);
    n || zt.set(s, n = []), n.push(e);
  }
}
function Xi(e, t, s = U) {
  const { immediate: n, deep: r, once: i, scheduler: l, augmentJob: o, call: c } = s, d = (O) => r ? O : /* @__PURE__ */ be(O) || r === !1 || r === 0 ? ke(O, 1) : ke(O);
  let u, p, w, C, k = !1, M = !1;
  if (/* @__PURE__ */ ie(e) ? (p = () => e.value, k = /* @__PURE__ */ be(e)) : /* @__PURE__ */ st(e) ? (p = () => d(e), k = !0) : P(e) ? (M = !0, k = e.some((O) => /* @__PURE__ */ st(O) || /* @__PURE__ */ be(O)), p = () => e.map((O) => {
    if (/* @__PURE__ */ ie(O))
      return O.value;
    if (/* @__PURE__ */ st(O))
      return d(O);
    if (R(O))
      return c ? c(O, 2) : O();
  })) : R(e) ? t ? p = c ? () => c(e, 2) : e : p = () => {
    if (w) {
      Le();
      try {
        w();
      } finally {
        Ve();
      }
    }
    const O = et;
    et = u;
    try {
      return c ? c(e, 3, [C]) : e(C);
    } finally {
      et = O;
    }
  } : p = Ie, t && r) {
    const O = p, Z = r === !0 ? 1 / 0 : r;
    p = () => ke(O(), Z);
  }
  const Y = wi(), q = () => {
    u.stop(), Y && Y.active && qs(Y.effects, u);
  };
  if (i && t) {
    const O = t;
    t = (...Z) => {
      O(...Z), q();
    };
  }
  let I = M ? new Array(e.length).fill(Bt) : Bt;
  const G = (O) => {
    if (!(!(u.flags & 1) || !u.dirty && !O))
      if (t) {
        const Z = u.run();
        if (r || k || (M ? Z.some((Ke, xe) => Me(Ke, I[xe])) : Me(Z, I))) {
          w && w();
          const Ke = et;
          et = u;
          try {
            const xe = [
              Z,
              // pass undefined as the old value when it's changed for the first time
              I === Bt ? void 0 : M && I[0] === Bt ? [] : I,
              C
            ];
            I = Z, c ? c(t, 3, xe) : (
              // @ts-expect-error
              t(...xe)
            );
          } finally {
            et = Ke;
          }
        }
      } else
        u.run();
  };
  return o && o(G), u = new cr(p), u.scheduler = l ? () => l(G, !1) : G, C = (O) => Yi(O, !1, u), w = u.onStop = () => {
    const O = zt.get(u);
    if (O) {
      if (c)
        c(O, 4);
      else
        for (const Z of O) Z();
      zt.delete(u);
    }
  }, t ? n ? G(!0) : I = u.run() : l ? l(G.bind(null, !0), !0) : u.run(), q.pause = u.pause.bind(u), q.resume = u.resume.bind(u), q.stop = q, q;
}
function ke(e, t = 1 / 0, s) {
  if (t <= 0 || !H(e) || e.__v_skip || (s = s || /* @__PURE__ */ new Map(), (s.get(e) || 0) >= t))
    return e;
  if (s.set(e, t), t--, /* @__PURE__ */ ie(e))
    ke(e.value, t, s);
  else if (P(e))
    for (let n = 0; n < e.length; n++)
      ke(e[n], t, s);
  else if (er(e) || ut(e))
    e.forEach((n) => {
      ke(n, t, s);
    });
  else if (nr(e)) {
    for (const n in e)
      ke(e[n], t, s);
    for (const n of Object.getOwnPropertySymbols(e))
      Object.prototype.propertyIsEnumerable.call(e, n) && ke(e[n], t, s);
  }
  return e;
}
/**
* @vue/runtime-core v3.5.31
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
function Dt(e, t, s, n) {
  try {
    return n ? e(...n) : e();
  } catch (r) {
    us(r, t, s);
  }
}
function Fe(e, t, s, n) {
  if (R(e)) {
    const r = Dt(e, t, s, n);
    return r && tr(r) && r.catch((i) => {
      us(i, t, s);
    }), r;
  }
  if (P(e)) {
    const r = [];
    for (let i = 0; i < e.length; i++)
      r.push(Fe(e[i], t, s, n));
    return r;
  }
}
function us(e, t, s, n = !0) {
  const r = t ? t.vnode : null, { errorHandler: i, throwUnhandledErrorInProduction: l } = t && t.appContext.config || U;
  if (t) {
    let o = t.parent;
    const c = t.proxy, d = `https://vuejs.org/error-reference/#runtime-${s}`;
    for (; o; ) {
      const u = o.ec;
      if (u) {
        for (let p = 0; p < u.length; p++)
          if (u[p](e, c, d) === !1)
            return;
      }
      o = o.parent;
    }
    if (i) {
      Le(), Dt(i, null, 10, [
        e,
        c,
        d
      ]), Ve();
      return;
    }
  }
  Zi(e, s, r, n, l);
}
function Zi(e, t, s, n = !0, r = !1) {
  if (r)
    throw e;
  console.error(e);
}
const fe = [];
let Ae = -1;
const at = [];
let qe = null, ot = 0;
const wr = /* @__PURE__ */ Promise.resolve();
let Yt = null;
function Qi(e) {
  const t = Yt || wr;
  return e ? t.then(this ? e.bind(this) : e) : t;
}
function el(e) {
  let t = Ae + 1, s = fe.length;
  for (; t < s; ) {
    const n = t + s >>> 1, r = fe[n], i = Rt(r);
    i < e || i === e && r.flags & 2 ? t = n + 1 : s = n;
  }
  return t;
}
function ln(e) {
  if (!(e.flags & 1)) {
    const t = Rt(e), s = fe[fe.length - 1];
    !s || // fast path when the job id is larger than the tail
    !(e.flags & 2) && t >= Rt(s) ? fe.push(e) : fe.splice(el(t), 0, e), e.flags |= 1, Cr();
  }
}
function Cr() {
  Yt || (Yt = wr.then(Er));
}
function tl(e) {
  P(e) ? at.push(...e) : qe && e.id === -1 ? qe.splice(ot + 1, 0, e) : e.flags & 1 || (at.push(e), e.flags |= 1), Cr();
}
function Sn(e, t, s = Ae + 1) {
  for (; s < fe.length; s++) {
    const n = fe[s];
    if (n && n.flags & 2) {
      if (e && n.id !== e.uid)
        continue;
      fe.splice(s, 1), s--, n.flags & 4 && (n.flags &= -2), n(), n.flags & 4 || (n.flags &= -2);
    }
  }
}
function Tr(e) {
  if (at.length) {
    const t = [...new Set(at)].sort(
      (s, n) => Rt(s) - Rt(n)
    );
    if (at.length = 0, qe) {
      qe.push(...t);
      return;
    }
    for (qe = t, ot = 0; ot < qe.length; ot++) {
      const s = qe[ot];
      s.flags & 4 && (s.flags &= -2), s.flags & 8 || s(), s.flags &= -2;
    }
    qe = null, ot = 0;
  }
}
const Rt = (e) => e.id == null ? e.flags & 2 ? -1 : 1 / 0 : e.id;
function Er(e) {
  try {
    for (Ae = 0; Ae < fe.length; Ae++) {
      const t = fe[Ae];
      t && !(t.flags & 8) && (t.flags & 4 && (t.flags &= -2), Dt(
        t,
        t.i,
        t.i ? 15 : 14
      ), t.flags & 4 || (t.flags &= -2));
    }
  } finally {
    for (; Ae < fe.length; Ae++) {
      const t = fe[Ae];
      t && (t.flags &= -2);
    }
    Ae = -1, fe.length = 0, Tr(), Yt = null, (fe.length || at.length) && Er();
  }
}
let ge = null, Ar = null;
function Xt(e) {
  const t = ge;
  return ge = e, Ar = e && e.type.__scopeId || null, t;
}
function sl(e, t = ge, s) {
  if (!t || e._n)
    return e;
  const n = (...r) => {
    n._d && es(-1);
    const i = Xt(t);
    let l;
    try {
      l = e(...r);
    } finally {
      Xt(i), n._d && es(1);
    }
    return l;
  };
  return n._n = !0, n._c = !0, n._d = !0, n;
}
function nl(e, t) {
  if (ge === null)
    return e;
  const s = ps(ge), n = e.dirs || (e.dirs = []);
  for (let r = 0; r < t.length; r++) {
    let [i, l, o, c = U] = t[r];
    i && (R(i) && (i = {
      mounted: i,
      updated: i
    }), i.deep && ke(l), n.push({
      dir: i,
      instance: s,
      value: l,
      oldValue: void 0,
      arg: o,
      modifiers: c
    }));
  }
  return e;
}
function Ze(e, t, s, n) {
  const r = e.dirs, i = t && t.dirs;
  for (let l = 0; l < r.length; l++) {
    const o = r[l];
    i && (o.oldValue = i[l].value);
    let c = o.dir[n];
    c && (Le(), Fe(c, s, 8, [
      e.el,
      o,
      e,
      t
    ]), Ve());
  }
}
function rl(e, t) {
  if (re) {
    let s = re.provides;
    const n = re.parent && re.parent.provides;
    n === s && (s = re.provides = Object.create(n)), s[e] = t;
  }
}
function Gt(e, t, s = !1) {
  const n = io();
  if (n || dt) {
    let r = dt ? dt._context.provides : n ? n.parent == null || n.ce ? n.vnode.appContext && n.vnode.appContext.provides : n.parent.provides : void 0;
    if (r && e in r)
      return r[e];
    if (arguments.length > 1)
      return s && R(t) ? t.call(n && n.proxy) : t;
  }
}
const il = /* @__PURE__ */ Symbol.for("v-scx"), ll = () => Gt(il);
function Ss(e, t, s) {
  return Pr(e, t, s);
}
function Pr(e, t, s = U) {
  const { immediate: n, deep: r, flush: i, once: l } = s, o = le({}, s), c = t && n || !t && i !== "post";
  let d;
  if ($t) {
    if (i === "sync") {
      const C = ll();
      d = C.__watcherHandles || (C.__watcherHandles = []);
    } else if (!c) {
      const C = () => {
      };
      return C.stop = Ie, C.resume = Ie, C.pause = Ie, C;
    }
  }
  const u = re;
  o.call = (C, k, M) => Fe(C, u, k, M);
  let p = !1;
  i === "post" ? o.scheduler = (C) => {
    de(C, u && u.suspense);
  } : i !== "sync" && (p = !0, o.scheduler = (C, k) => {
    k ? C() : ln(C);
  }), o.augmentJob = (C) => {
    t && (C.flags |= 4), p && (C.flags |= 2, u && (C.id = u.uid, C.i = u));
  };
  const w = Xi(e, t, o);
  return $t && (d ? d.push(w) : c && w()), w;
}
function ol(e, t, s) {
  const n = this.proxy, r = z(e) ? e.includes(".") ? Or(n, e) : () => n[e] : e.bind(n, n);
  let i;
  R(t) ? i = t : (i = t.handler, s = t);
  const l = Nt(this), o = Pr(r, i.bind(n), s);
  return l(), o;
}
function Or(e, t) {
  const s = t.split(".");
  return () => {
    let n = e;
    for (let r = 0; r < s.length && n; r++)
      n = n[s[r]];
    return n;
  };
}
const cl = /* @__PURE__ */ Symbol("_vte"), fl = (e) => e.__isTeleport, ul = /* @__PURE__ */ Symbol("_leaveCb");
function on(e, t) {
  e.shapeFlag & 6 && e.component ? (e.transition = t, on(e.component.subTree, t)) : e.shapeFlag & 128 ? (e.ssContent.transition = t.clone(e.ssContent), e.ssFallback.transition = t.clone(e.ssFallback)) : e.transition = t;
}
function Mr(e) {
  e.ids = [e.ids[0] + e.ids[2]++ + "-", 0, 0];
}
function wn(e, t) {
  let s;
  return !!((s = Object.getOwnPropertyDescriptor(e, t)) && !s.configurable);
}
const Zt = /* @__PURE__ */ new WeakMap();
function Tt(e, t, s, n, r = !1) {
  if (P(e)) {
    e.forEach(
      (M, Y) => Tt(
        M,
        t && (P(t) ? t[Y] : t),
        s,
        n,
        r
      )
    );
    return;
  }
  if (Et(n) && !r) {
    n.shapeFlag & 512 && n.type.__asyncResolved && n.component.subTree.component && Tt(e, t, s, n.component.subTree);
    return;
  }
  const i = n.shapeFlag & 4 ? ps(n.component) : n.el, l = r ? null : i, { i: o, r: c } = e, d = t && t.r, u = o.refs === U ? o.refs = {} : o.refs, p = o.setupState, w = /* @__PURE__ */ F(p), C = p === U ? Qn : (M) => wn(u, M) ? !1 : D(w, M), k = (M, Y) => !(Y && wn(u, Y));
  if (d != null && d !== c) {
    if (Cn(t), z(d))
      u[d] = null, C(d) && (p[d] = null);
    else if (/* @__PURE__ */ ie(d)) {
      const M = t;
      k(d, M.k) && (d.value = null), M.k && (u[M.k] = null);
    }
  }
  if (R(c))
    Dt(c, o, 12, [l, u]);
  else {
    const M = z(c), Y = /* @__PURE__ */ ie(c);
    if (M || Y) {
      const q = () => {
        if (e.f) {
          const I = M ? C(c) ? p[c] : u[c] : k() || !e.k ? c.value : u[e.k];
          if (r)
            P(I) && qs(I, i);
          else if (P(I))
            I.includes(i) || I.push(i);
          else if (M)
            u[c] = [i], C(c) && (p[c] = u[c]);
          else {
            const G = [i];
            k(c, e.k) && (c.value = G), e.k && (u[e.k] = G);
          }
        } else M ? (u[c] = l, C(c) && (p[c] = l)) : Y && (k(c, e.k) && (c.value = l), e.k && (u[e.k] = l));
      };
      if (l) {
        const I = () => {
          q(), Zt.delete(e);
        };
        I.id = -1, Zt.set(e, I), de(I, s);
      } else
        Cn(e), q();
    }
  }
}
function Cn(e) {
  const t = Zt.get(e);
  t && (t.flags |= 8, Zt.delete(e));
}
os().requestIdleCallback;
os().cancelIdleCallback;
const Et = (e) => !!e.type.__asyncLoader, Rr = (e) => e.type.__isKeepAlive;
function al(e, t) {
  Ir(e, "a", t);
}
function dl(e, t) {
  Ir(e, "da", t);
}
function Ir(e, t, s = re) {
  const n = e.__wdc || (e.__wdc = () => {
    let r = s;
    for (; r; ) {
      if (r.isDeactivated)
        return;
      r = r.parent;
    }
    return e();
  });
  if (as(t, n, s), s) {
    let r = s.parent;
    for (; r && r.parent; )
      Rr(r.parent.vnode) && hl(n, t, s, r), r = r.parent;
  }
}
function hl(e, t, s, n) {
  const r = as(
    t,
    e,
    n,
    !0
    /* prepend */
  );
  $r(() => {
    qs(n[t], r);
  }, s);
}
function as(e, t, s = re, n = !1) {
  if (s) {
    const r = s[e] || (s[e] = []), i = t.__weh || (t.__weh = (...l) => {
      Le();
      const o = Nt(s), c = Fe(t, s, e, l);
      return o(), Ve(), c;
    });
    return n ? r.unshift(i) : r.push(i), i;
  }
}
const We = (e) => (t, s = re) => {
  (!$t || e === "sp") && as(e, (...n) => t(...n), s);
}, pl = We("bm"), gl = We("m"), _l = We(
  "bu"
), ml = We("u"), bl = We(
  "bum"
), $r = We("um"), vl = We(
  "sp"
), yl = We("rtg"), xl = We("rtc");
function Sl(e, t = re) {
  as("ec", e, t);
}
const wl = "components", Fr = /* @__PURE__ */ Symbol.for("v-ndc");
function Cl(e) {
  return z(e) ? Tl(wl, e, !1) || e : e || Fr;
}
function Tl(e, t, s = !0, n = !1) {
  const r = ge || re;
  if (r) {
    const i = r.type;
    {
      const o = uo(
        i,
        !1
      );
      if (o && (o === t || o === ae(t) || o === ls(ae(t))))
        return i;
    }
    const l = (
      // local registration
      // check instance[type] first which is resolved for options API
      Tn(r[e] || i[e], t) || // global registration
      Tn(r.appContext[e], t)
    );
    return !l && n ? i : l;
  }
}
function Tn(e, t) {
  return e && (e[t] || e[ae(t)] || e[ls(ae(t))]);
}
function nt(e, t, s, n) {
  let r;
  const i = s, l = P(e);
  if (l || z(e)) {
    const o = l && /* @__PURE__ */ st(e);
    let c = !1, d = !1;
    o && (c = !/* @__PURE__ */ be(e), d = /* @__PURE__ */ Ue(e), e = cs(e)), r = new Array(e.length);
    for (let u = 0, p = e.length; u < p; u++)
      r[u] = t(
        c ? d ? ht(ye(e[u])) : ye(e[u]) : e[u],
        u,
        void 0,
        i
      );
  } else if (typeof e == "number") {
    r = new Array(e);
    for (let o = 0; o < e; o++)
      r[o] = t(o + 1, o, void 0, i);
  } else if (H(e))
    if (e[Symbol.iterator])
      r = Array.from(
        e,
        (o, c) => t(o, c, void 0, i)
      );
    else {
      const o = Object.keys(e);
      r = new Array(o.length);
      for (let c = 0, d = o.length; c < d; c++) {
        const u = o[c];
        r[c] = t(e[u], u, c, i);
      }
    }
  else
    r = [];
  return r;
}
const Ds = (e) => e ? ti(e) ? ps(e) : Ds(e.parent) : null, At = (
  // Move PURE marker to new line to workaround compiler discarding it
  // due to type annotation
  /* @__PURE__ */ le(/* @__PURE__ */ Object.create(null), {
    $: (e) => e,
    $el: (e) => e.vnode.el,
    $data: (e) => e.data,
    $props: (e) => e.props,
    $attrs: (e) => e.attrs,
    $slots: (e) => e.slots,
    $refs: (e) => e.refs,
    $parent: (e) => Ds(e.parent),
    $root: (e) => Ds(e.root),
    $host: (e) => e.ce,
    $emit: (e) => e.emit,
    $options: (e) => Nr(e),
    $forceUpdate: (e) => e.f || (e.f = () => {
      ln(e.update);
    }),
    $nextTick: (e) => e.n || (e.n = Qi.bind(e.proxy)),
    $watch: (e) => ol.bind(e)
  })
), ws = (e, t) => e !== U && !e.__isScriptSetup && D(e, t), El = {
  get({ _: e }, t) {
    if (t === "__v_skip")
      return !0;
    const { ctx: s, setupState: n, data: r, props: i, accessCache: l, type: o, appContext: c } = e;
    if (t[0] !== "$") {
      const w = l[t];
      if (w !== void 0)
        switch (w) {
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
        if (ws(n, t))
          return l[t] = 1, n[t];
        if (r !== U && D(r, t))
          return l[t] = 2, r[t];
        if (D(i, t))
          return l[t] = 3, i[t];
        if (s !== U && D(s, t))
          return l[t] = 4, s[t];
        Ns && (l[t] = 0);
      }
    }
    const d = At[t];
    let u, p;
    if (d)
      return t === "$attrs" && se(e.attrs, "get", ""), d(e);
    if (
      // css module (injected by vue-loader)
      (u = o.__cssModules) && (u = u[t])
    )
      return u;
    if (s !== U && D(s, t))
      return l[t] = 4, s[t];
    if (
      // global properties
      p = c.config.globalProperties, D(p, t)
    )
      return p[t];
  },
  set({ _: e }, t, s) {
    const { data: n, setupState: r, ctx: i } = e;
    return ws(r, t) ? (r[t] = s, !0) : n !== U && D(n, t) ? (n[t] = s, !0) : D(e.props, t) || t[0] === "$" && t.slice(1) in e ? !1 : (i[t] = s, !0);
  },
  has({
    _: { data: e, setupState: t, accessCache: s, ctx: n, appContext: r, props: i, type: l }
  }, o) {
    let c;
    return !!(s[o] || e !== U && o[0] !== "$" && D(e, o) || ws(t, o) || D(i, o) || D(n, o) || D(At, o) || D(r.config.globalProperties, o) || (c = l.__cssModules) && c[o]);
  },
  defineProperty(e, t, s) {
    return s.get != null ? e._.accessCache[t] = 0 : D(s, "value") && this.set(e, t, s.value, null), Reflect.defineProperty(e, t, s);
  }
};
function En(e) {
  return P(e) ? e.reduce(
    (t, s) => (t[s] = null, t),
    {}
  ) : e;
}
let Ns = !0;
function Al(e) {
  const t = Nr(e), s = e.proxy, n = e.ctx;
  Ns = !1, t.beforeCreate && An(t.beforeCreate, e, "bc");
  const {
    // state
    data: r,
    computed: i,
    methods: l,
    watch: o,
    provide: c,
    inject: d,
    // lifecycle
    created: u,
    beforeMount: p,
    mounted: w,
    beforeUpdate: C,
    updated: k,
    activated: M,
    deactivated: Y,
    beforeDestroy: q,
    beforeUnmount: I,
    destroyed: G,
    unmounted: O,
    render: Z,
    renderTracked: Ke,
    renderTriggered: xe,
    errorCaptured: Ge,
    serverPrefetch: jt,
    // public API
    expose: ze,
    inheritAttrs: gt,
    // assets
    components: Ht,
    directives: kt,
    filters: gs
  } = t;
  if (d && Pl(d, n, null), l)
    for (const J in l) {
      const W = l[J];
      R(W) && (n[J] = W.bind(s));
    }
  if (r) {
    const J = r.call(s, s);
    H(J) && (e.data = /* @__PURE__ */ fs(J));
  }
  if (Ns = !0, i)
    for (const J in i) {
      const W = i[J], Ye = R(W) ? W.bind(s, s) : R(W.get) ? W.get.bind(s, s) : Ie, Lt = !R(W) && R(W.set) ? W.set.bind(s) : Ie, Xe = ue({
        get: Ye,
        set: Lt
      });
      Object.defineProperty(n, J, {
        enumerable: !0,
        configurable: !0,
        get: () => Xe.value,
        set: (Se) => Xe.value = Se
      });
    }
  if (o)
    for (const J in o)
      Dr(o[J], n, s, J);
  if (c) {
    const J = R(c) ? c.call(s) : c;
    Reflect.ownKeys(J).forEach((W) => {
      rl(W, J[W]);
    });
  }
  u && An(u, e, "c");
  function oe(J, W) {
    P(W) ? W.forEach((Ye) => J(Ye.bind(s))) : W && J(W.bind(s));
  }
  if (oe(pl, p), oe(gl, w), oe(_l, C), oe(ml, k), oe(al, M), oe(dl, Y), oe(Sl, Ge), oe(xl, Ke), oe(yl, xe), oe(bl, I), oe($r, O), oe(vl, jt), P(ze))
    if (ze.length) {
      const J = e.exposed || (e.exposed = {});
      ze.forEach((W) => {
        Object.defineProperty(J, W, {
          get: () => s[W],
          set: (Ye) => s[W] = Ye,
          enumerable: !0
        });
      });
    } else e.exposed || (e.exposed = {});
  Z && e.render === Ie && (e.render = Z), gt != null && (e.inheritAttrs = gt), Ht && (e.components = Ht), kt && (e.directives = kt), jt && Mr(e);
}
function Pl(e, t, s = Ie) {
  P(e) && (e = js(e));
  for (const n in e) {
    const r = e[n];
    let i;
    H(r) ? "default" in r ? i = Gt(
      r.from || n,
      r.default,
      !0
    ) : i = Gt(r.from || n) : i = Gt(r), /* @__PURE__ */ ie(i) ? Object.defineProperty(t, n, {
      enumerable: !0,
      configurable: !0,
      get: () => i.value,
      set: (l) => i.value = l
    }) : t[n] = i;
  }
}
function An(e, t, s) {
  Fe(
    P(e) ? e.map((n) => n.bind(t.proxy)) : e.bind(t.proxy),
    t,
    s
  );
}
function Dr(e, t, s, n) {
  let r = n.includes(".") ? Or(s, n) : () => s[n];
  if (z(e)) {
    const i = t[e];
    R(i) && Ss(r, i);
  } else if (R(e))
    Ss(r, e.bind(s));
  else if (H(e))
    if (P(e))
      e.forEach((i) => Dr(i, t, s, n));
    else {
      const i = R(e.handler) ? e.handler.bind(s) : t[e.handler];
      R(i) && Ss(r, i, e);
    }
}
function Nr(e) {
  const t = e.type, { mixins: s, extends: n } = t, {
    mixins: r,
    optionsCache: i,
    config: { optionMergeStrategies: l }
  } = e.appContext, o = i.get(t);
  let c;
  return o ? c = o : !r.length && !s && !n ? c = t : (c = {}, r.length && r.forEach(
    (d) => Qt(c, d, l, !0)
  ), Qt(c, t, l)), H(t) && i.set(t, c), c;
}
function Qt(e, t, s, n = !1) {
  const { mixins: r, extends: i } = t;
  i && Qt(e, i, s, !0), r && r.forEach(
    (l) => Qt(e, l, s, !0)
  );
  for (const l in t)
    if (!(n && l === "expose")) {
      const o = Ol[l] || s && s[l];
      e[l] = o ? o(e[l], t[l]) : t[l];
    }
  return e;
}
const Ol = {
  data: Pn,
  props: On,
  emits: On,
  // objects
  methods: xt,
  computed: xt,
  // lifecycle
  beforeCreate: ce,
  created: ce,
  beforeMount: ce,
  mounted: ce,
  beforeUpdate: ce,
  updated: ce,
  beforeDestroy: ce,
  beforeUnmount: ce,
  destroyed: ce,
  unmounted: ce,
  activated: ce,
  deactivated: ce,
  errorCaptured: ce,
  serverPrefetch: ce,
  // assets
  components: xt,
  directives: xt,
  // watch
  watch: Rl,
  // provide / inject
  provide: Pn,
  inject: Ml
};
function Pn(e, t) {
  return t ? e ? function() {
    return le(
      R(e) ? e.call(this, this) : e,
      R(t) ? t.call(this, this) : t
    );
  } : t : e;
}
function Ml(e, t) {
  return xt(js(e), js(t));
}
function js(e) {
  if (P(e)) {
    const t = {};
    for (let s = 0; s < e.length; s++)
      t[e[s]] = e[s];
    return t;
  }
  return e;
}
function ce(e, t) {
  return e ? [...new Set([].concat(e, t))] : t;
}
function xt(e, t) {
  return e ? le(/* @__PURE__ */ Object.create(null), e, t) : t;
}
function On(e, t) {
  return e ? P(e) && P(t) ? [.../* @__PURE__ */ new Set([...e, ...t])] : le(
    /* @__PURE__ */ Object.create(null),
    En(e),
    En(t ?? {})
  ) : t;
}
function Rl(e, t) {
  if (!e) return t;
  if (!t) return e;
  const s = le(/* @__PURE__ */ Object.create(null), e);
  for (const n in t)
    s[n] = ce(e[n], t[n]);
  return s;
}
function jr() {
  return {
    app: null,
    config: {
      isNativeTag: Qn,
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
let Il = 0;
function $l(e, t) {
  return function(n, r = null) {
    R(n) || (n = le({}, n)), r != null && !H(r) && (r = null);
    const i = jr(), l = /* @__PURE__ */ new WeakSet(), o = [];
    let c = !1;
    const d = i.app = {
      _uid: Il++,
      _component: n,
      _props: r,
      _container: null,
      _context: i,
      _instance: null,
      version: po,
      get config() {
        return i.config;
      },
      set config(u) {
      },
      use(u, ...p) {
        return l.has(u) || (u && R(u.install) ? (l.add(u), u.install(d, ...p)) : R(u) && (l.add(u), u(d, ...p))), d;
      },
      mixin(u) {
        return i.mixins.includes(u) || i.mixins.push(u), d;
      },
      component(u, p) {
        return p ? (i.components[u] = p, d) : i.components[u];
      },
      directive(u, p) {
        return p ? (i.directives[u] = p, d) : i.directives[u];
      },
      mount(u, p, w) {
        if (!c) {
          const C = d._ceVNode || me(n, r);
          return C.appContext = i, w === !0 ? w = "svg" : w === !1 && (w = void 0), e(C, u, w), c = !0, d._container = u, u.__vue_app__ = d, ps(C.component);
        }
      },
      onUnmount(u) {
        o.push(u);
      },
      unmount() {
        c && (Fe(
          o,
          d._instance,
          16
        ), e(null, d._container), delete d._container.__vue_app__);
      },
      provide(u, p) {
        return i.provides[u] = p, d;
      },
      runWithContext(u) {
        const p = dt;
        dt = d;
        try {
          return u();
        } finally {
          dt = p;
        }
      }
    };
    return d;
  };
}
let dt = null;
const Fl = (e, t) => t === "modelValue" || t === "model-value" ? e.modelModifiers : e[`${t}Modifiers`] || e[`${ae(t)}Modifiers`] || e[`${rt(t)}Modifiers`];
function Dl(e, t, ...s) {
  if (e.isUnmounted) return;
  const n = e.vnode.props || U;
  let r = s;
  const i = t.startsWith("update:"), l = i && Fl(n, t.slice(7));
  l && (l.trim && (r = s.map((u) => z(u) ? u.trim() : u)), l.number && (r = s.map(zs)));
  let o, c = n[o = ms(t)] || // also try camelCase event handler (#2249)
  n[o = ms(ae(t))];
  !c && i && (c = n[o = ms(rt(t))]), c && Fe(
    c,
    e,
    6,
    r
  );
  const d = n[o + "Once"];
  if (d) {
    if (!e.emitted)
      e.emitted = {};
    else if (e.emitted[o])
      return;
    e.emitted[o] = !0, Fe(
      d,
      e,
      6,
      r
    );
  }
}
const Nl = /* @__PURE__ */ new WeakMap();
function Hr(e, t, s = !1) {
  const n = s ? Nl : t.emitsCache, r = n.get(e);
  if (r !== void 0)
    return r;
  const i = e.emits;
  let l = {}, o = !1;
  if (!R(e)) {
    const c = (d) => {
      const u = Hr(d, t, !0);
      u && (o = !0, le(l, u));
    };
    !s && t.mixins.length && t.mixins.forEach(c), e.extends && c(e.extends), e.mixins && e.mixins.forEach(c);
  }
  return !i && !o ? (H(e) && n.set(e, null), null) : (P(i) ? i.forEach((c) => l[c] = null) : le(l, i), H(e) && n.set(e, l), l);
}
function ds(e, t) {
  return !e || !ns(t) ? !1 : (t = t.slice(2).replace(/Once$/, ""), D(e, t[0].toLowerCase() + t.slice(1)) || D(e, rt(t)) || D(e, t));
}
function Mn(e) {
  const {
    type: t,
    vnode: s,
    proxy: n,
    withProxy: r,
    propsOptions: [i],
    slots: l,
    attrs: o,
    emit: c,
    render: d,
    renderCache: u,
    props: p,
    data: w,
    setupState: C,
    ctx: k,
    inheritAttrs: M
  } = e, Y = Xt(e);
  let q, I;
  try {
    if (s.shapeFlag & 4) {
      const O = r || n, Z = O;
      q = Oe(
        d.call(
          Z,
          O,
          u,
          p,
          C,
          w,
          k
        )
      ), I = o;
    } else {
      const O = t;
      q = Oe(
        O.length > 1 ? O(
          p,
          { attrs: o, slots: l, emit: c }
        ) : O(
          p,
          null
        )
      ), I = t.props ? o : jl(o);
    }
  } catch (O) {
    Pt.length = 0, us(O, e, 1), q = me(Je);
  }
  let G = q;
  if (I && M !== !1) {
    const O = Object.keys(I), { shapeFlag: Z } = G;
    O.length && Z & 7 && (i && O.some(rs) && (I = Hl(
      I,
      i
    )), G = pt(G, I, !1, !0));
  }
  return s.dirs && (G = pt(G, null, !1, !0), G.dirs = G.dirs ? G.dirs.concat(s.dirs) : s.dirs), s.transition && on(G, s.transition), q = G, Xt(Y), q;
}
const jl = (e) => {
  let t;
  for (const s in e)
    (s === "class" || s === "style" || ns(s)) && ((t || (t = {}))[s] = e[s]);
  return t;
}, Hl = (e, t) => {
  const s = {};
  for (const n in e)
    (!rs(n) || !(n.slice(9) in t)) && (s[n] = e[n]);
  return s;
};
function kl(e, t, s) {
  const { props: n, children: r, component: i } = e, { props: l, children: o, patchFlag: c } = t, d = i.emitsOptions;
  if (t.dirs || t.transition)
    return !0;
  if (s && c >= 0) {
    if (c & 1024)
      return !0;
    if (c & 16)
      return n ? Rn(n, l, d) : !!l;
    if (c & 8) {
      const u = t.dynamicProps;
      for (let p = 0; p < u.length; p++) {
        const w = u[p];
        if (kr(l, n, w) && !ds(d, w))
          return !0;
      }
    }
  } else
    return (r || o) && (!o || !o.$stable) ? !0 : n === l ? !1 : n ? l ? Rn(n, l, d) : !0 : !!l;
  return !1;
}
function Rn(e, t, s) {
  const n = Object.keys(t);
  if (n.length !== Object.keys(e).length)
    return !0;
  for (let r = 0; r < n.length; r++) {
    const i = n[r];
    if (kr(t, e, i) && !ds(s, i))
      return !0;
  }
  return !1;
}
function kr(e, t, s) {
  const n = e[s], r = t[s];
  return s === "style" && H(n) && H(r) ? !Xs(n, r) : n !== r;
}
function Ll({ vnode: e, parent: t, suspense: s }, n) {
  for (; t; ) {
    const r = t.subTree;
    if (r.suspense && r.suspense.activeBranch === e && (r.suspense.vnode.el = r.el = n, e = r), r === e)
      (e = t.vnode).el = n, t = t.parent;
    else
      break;
  }
  s && s.activeBranch === e && (s.vnode.el = n);
}
const Lr = {}, Vr = () => Object.create(Lr), Ur = (e) => Object.getPrototypeOf(e) === Lr;
function Vl(e, t, s, n = !1) {
  const r = {}, i = Vr();
  e.propsDefaults = /* @__PURE__ */ Object.create(null), Wr(e, t, r, i);
  for (const l in e.propsOptions[0])
    l in r || (r[l] = void 0);
  s ? e.props = n ? r : /* @__PURE__ */ Ui(r) : e.type.props ? e.props = r : e.props = i, e.attrs = i;
}
function Ul(e, t, s, n) {
  const {
    props: r,
    attrs: i,
    vnode: { patchFlag: l }
  } = e, o = /* @__PURE__ */ F(r), [c] = e.propsOptions;
  let d = !1;
  if (
    // always force full diff in dev
    // - #1942 if hmr is enabled with sfc component
    // - vite#872 non-sfc component used by sfc component
    (n || l > 0) && !(l & 16)
  ) {
    if (l & 8) {
      const u = e.vnode.dynamicProps;
      for (let p = 0; p < u.length; p++) {
        let w = u[p];
        if (ds(e.emitsOptions, w))
          continue;
        const C = t[w];
        if (c)
          if (D(i, w))
            C !== i[w] && (i[w] = C, d = !0);
          else {
            const k = ae(w);
            r[k] = Hs(
              c,
              o,
              k,
              C,
              e,
              !1
            );
          }
        else
          C !== i[w] && (i[w] = C, d = !0);
      }
    }
  } else {
    Wr(e, t, r, i) && (d = !0);
    let u;
    for (const p in o)
      (!t || // for camelCase
      !D(t, p) && // it's possible the original props was passed in as kebab-case
      // and converted to camelCase (#955)
      ((u = rt(p)) === p || !D(t, u))) && (c ? s && // for camelCase
      (s[p] !== void 0 || // for kebab-case
      s[u] !== void 0) && (r[p] = Hs(
        c,
        o,
        p,
        void 0,
        e,
        !0
      )) : delete r[p]);
    if (i !== o)
      for (const p in i)
        (!t || !D(t, p)) && (delete i[p], d = !0);
  }
  d && He(e.attrs, "set", "");
}
function Wr(e, t, s, n) {
  const [r, i] = e.propsOptions;
  let l = !1, o;
  if (t)
    for (let c in t) {
      if (St(c))
        continue;
      const d = t[c];
      let u;
      r && D(r, u = ae(c)) ? !i || !i.includes(u) ? s[u] = d : (o || (o = {}))[u] = d : ds(e.emitsOptions, c) || (!(c in n) || d !== n[c]) && (n[c] = d, l = !0);
    }
  if (i) {
    const c = /* @__PURE__ */ F(s), d = o || U;
    for (let u = 0; u < i.length; u++) {
      const p = i[u];
      s[p] = Hs(
        r,
        c,
        p,
        d[p],
        e,
        !D(d, p)
      );
    }
  }
  return l;
}
function Hs(e, t, s, n, r, i) {
  const l = e[s];
  if (l != null) {
    const o = D(l, "default");
    if (o && n === void 0) {
      const c = l.default;
      if (l.type !== Function && !l.skipFactory && R(c)) {
        const { propsDefaults: d } = r;
        if (s in d)
          n = d[s];
        else {
          const u = Nt(r);
          n = d[s] = c.call(
            null,
            t
          ), u();
        }
      } else
        n = c;
      r.ce && r.ce._setProp(s, n);
    }
    l[
      0
      /* shouldCast */
    ] && (i && !o ? n = !1 : l[
      1
      /* shouldCastTrue */
    ] && (n === "" || n === rt(s)) && (n = !0));
  }
  return n;
}
const Wl = /* @__PURE__ */ new WeakMap();
function Br(e, t, s = !1) {
  const n = s ? Wl : t.propsCache, r = n.get(e);
  if (r)
    return r;
  const i = e.props, l = {}, o = [];
  let c = !1;
  if (!R(e)) {
    const u = (p) => {
      c = !0;
      const [w, C] = Br(p, t, !0);
      le(l, w), C && o.push(...C);
    };
    !s && t.mixins.length && t.mixins.forEach(u), e.extends && u(e.extends), e.mixins && e.mixins.forEach(u);
  }
  if (!i && !c)
    return H(e) && n.set(e, ft), ft;
  if (P(i))
    for (let u = 0; u < i.length; u++) {
      const p = ae(i[u]);
      In(p) && (l[p] = U);
    }
  else if (i)
    for (const u in i) {
      const p = ae(u);
      if (In(p)) {
        const w = i[u], C = l[p] = P(w) || R(w) ? { type: w } : le({}, w), k = C.type;
        let M = !1, Y = !0;
        if (P(k))
          for (let q = 0; q < k.length; ++q) {
            const I = k[q], G = R(I) && I.name;
            if (G === "Boolean") {
              M = !0;
              break;
            } else G === "String" && (Y = !1);
          }
        else
          M = R(k) && k.name === "Boolean";
        C[
          0
          /* shouldCast */
        ] = M, C[
          1
          /* shouldCastTrue */
        ] = Y, (M || D(C, "default")) && o.push(p);
      }
    }
  const d = [l, o];
  return H(e) && n.set(e, d), d;
}
function In(e) {
  return e[0] !== "$" && !St(e);
}
const cn = (e) => e === "_" || e === "_ctx" || e === "$stable", fn = (e) => P(e) ? e.map(Oe) : [Oe(e)], Bl = (e, t, s) => {
  if (t._n)
    return t;
  const n = sl((...r) => fn(t(...r)), s);
  return n._c = !1, n;
}, Kr = (e, t, s) => {
  const n = e._ctx;
  for (const r in e) {
    if (cn(r)) continue;
    const i = e[r];
    if (R(i))
      t[r] = Bl(r, i, n);
    else if (i != null) {
      const l = fn(i);
      t[r] = () => l;
    }
  }
}, Gr = (e, t) => {
  const s = fn(t);
  e.slots.default = () => s;
}, qr = (e, t, s) => {
  for (const n in t)
    (s || !cn(n)) && (e[n] = t[n]);
}, Kl = (e, t, s) => {
  const n = e.slots = Vr();
  if (e.vnode.shapeFlag & 32) {
    const r = t._;
    r ? (qr(n, t, s), s && rr(n, "_", r, !0)) : Kr(t, n);
  } else t && Gr(e, t);
}, Gl = (e, t, s) => {
  const { vnode: n, slots: r } = e;
  let i = !0, l = U;
  if (n.shapeFlag & 32) {
    const o = t._;
    o ? s && o === 1 ? i = !1 : qr(r, t, s) : (i = !t.$stable, Kr(t, r)), l = t;
  } else t && (Gr(e, t), l = { default: 1 });
  if (i)
    for (const o in r)
      !cn(o) && l[o] == null && delete r[o];
}, de = Xl;
function ql(e) {
  return Jl(e);
}
function Jl(e, t) {
  const s = os();
  s.__VUE__ = !0;
  const {
    insert: n,
    remove: r,
    patchProp: i,
    createElement: l,
    createText: o,
    createComment: c,
    setText: d,
    setElementText: u,
    parentNode: p,
    nextSibling: w,
    setScopeId: C = Ie,
    insertStaticContent: k
  } = e, M = (f, a, h, b = null, g = null, _ = null, x = void 0, y = null, v = !!a.dynamicChildren) => {
    if (f === a)
      return;
    f && !yt(f, a) && (b = Vt(f), Se(f, g, _, !0), f = null), a.patchFlag === -2 && (v = !1, a.dynamicChildren = null);
    const { type: m, ref: E, shapeFlag: S } = a;
    switch (m) {
      case hs:
        Y(f, a, h, b);
        break;
      case Je:
        q(f, a, h, b);
        break;
      case Ts:
        f == null && I(a, h, b, x);
        break;
      case ne:
        Ht(
          f,
          a,
          h,
          b,
          g,
          _,
          x,
          y,
          v
        );
        break;
      default:
        S & 1 ? Z(
          f,
          a,
          h,
          b,
          g,
          _,
          x,
          y,
          v
        ) : S & 6 ? kt(
          f,
          a,
          h,
          b,
          g,
          _,
          x,
          y,
          v
        ) : (S & 64 || S & 128) && m.process(
          f,
          a,
          h,
          b,
          g,
          _,
          x,
          y,
          v,
          mt
        );
    }
    E != null && g ? Tt(E, f && f.ref, _, a || f, !a) : E == null && f && f.ref != null && Tt(f.ref, null, _, f, !0);
  }, Y = (f, a, h, b) => {
    if (f == null)
      n(
        a.el = o(a.children),
        h,
        b
      );
    else {
      const g = a.el = f.el;
      a.children !== f.children && d(g, a.children);
    }
  }, q = (f, a, h, b) => {
    f == null ? n(
      a.el = c(a.children || ""),
      h,
      b
    ) : a.el = f.el;
  }, I = (f, a, h, b) => {
    [f.el, f.anchor] = k(
      f.children,
      a,
      h,
      b,
      f.el,
      f.anchor
    );
  }, G = ({ el: f, anchor: a }, h, b) => {
    let g;
    for (; f && f !== a; )
      g = w(f), n(f, h, b), f = g;
    n(a, h, b);
  }, O = ({ el: f, anchor: a }) => {
    let h;
    for (; f && f !== a; )
      h = w(f), r(f), f = h;
    r(a);
  }, Z = (f, a, h, b, g, _, x, y, v) => {
    if (a.type === "svg" ? x = "svg" : a.type === "math" && (x = "mathml"), f == null)
      Ke(
        a,
        h,
        b,
        g,
        _,
        x,
        y,
        v
      );
    else {
      const m = f.el && f.el._isVueCE ? f.el : null;
      try {
        m && m._beginPatch(), jt(
          f,
          a,
          g,
          _,
          x,
          y,
          v
        );
      } finally {
        m && m._endPatch();
      }
    }
  }, Ke = (f, a, h, b, g, _, x, y) => {
    let v, m;
    const { props: E, shapeFlag: S, transition: T, dirs: A } = f;
    if (v = f.el = l(
      f.type,
      _,
      E && E.is,
      E
    ), S & 8 ? u(v, f.children) : S & 16 && Ge(
      f.children,
      v,
      null,
      b,
      g,
      Cs(f, _),
      x,
      y
    ), A && Ze(f, null, b, "created"), xe(v, f, f.scopeId, x, b), E) {
      for (const V in E)
        V !== "value" && !St(V) && i(v, V, null, E[V], _, b);
      "value" in E && i(v, "value", null, E.value, _), (m = E.onVnodeBeforeMount) && Ee(m, b, f);
    }
    A && Ze(f, null, b, "beforeMount");
    const $ = zl(g, T);
    $ && T.beforeEnter(v), n(v, a, h), ((m = E && E.onVnodeMounted) || $ || A) && de(() => {
      try {
        m && Ee(m, b, f), $ && T.enter(v), A && Ze(f, null, b, "mounted");
      } finally {
      }
    }, g);
  }, xe = (f, a, h, b, g) => {
    if (h && C(f, h), b)
      for (let _ = 0; _ < b.length; _++)
        C(f, b[_]);
    if (g) {
      let _ = g.subTree;
      if (a === _ || Xr(_.type) && (_.ssContent === a || _.ssFallback === a)) {
        const x = g.vnode;
        xe(
          f,
          x,
          x.scopeId,
          x.slotScopeIds,
          g.parent
        );
      }
    }
  }, Ge = (f, a, h, b, g, _, x, y, v = 0) => {
    for (let m = v; m < f.length; m++) {
      const E = f[m] = y ? je(f[m]) : Oe(f[m]);
      M(
        null,
        E,
        a,
        h,
        b,
        g,
        _,
        x,
        y
      );
    }
  }, jt = (f, a, h, b, g, _, x) => {
    const y = a.el = f.el;
    let { patchFlag: v, dynamicChildren: m, dirs: E } = a;
    v |= f.patchFlag & 16;
    const S = f.props || U, T = a.props || U;
    let A;
    if (h && Qe(h, !1), (A = T.onVnodeBeforeUpdate) && Ee(A, h, a, f), E && Ze(a, f, h, "beforeUpdate"), h && Qe(h, !0), (S.innerHTML && T.innerHTML == null || S.textContent && T.textContent == null) && u(y, ""), m ? ze(
      f.dynamicChildren,
      m,
      y,
      h,
      b,
      Cs(a, g),
      _
    ) : x || W(
      f,
      a,
      y,
      null,
      h,
      b,
      Cs(a, g),
      _,
      !1
    ), v > 0) {
      if (v & 16)
        gt(y, S, T, h, g);
      else if (v & 2 && S.class !== T.class && i(y, "class", null, T.class, g), v & 4 && i(y, "style", S.style, T.style, g), v & 8) {
        const $ = a.dynamicProps;
        for (let V = 0; V < $.length; V++) {
          const B = $[V], X = S[B], te = T[B];
          (te !== X || B === "value") && i(y, B, X, te, g, h);
        }
      }
      v & 1 && f.children !== a.children && u(y, a.children);
    } else !x && m == null && gt(y, S, T, h, g);
    ((A = T.onVnodeUpdated) || E) && de(() => {
      A && Ee(A, h, a, f), E && Ze(a, f, h, "updated");
    }, b);
  }, ze = (f, a, h, b, g, _, x) => {
    for (let y = 0; y < a.length; y++) {
      const v = f[y], m = a[y], E = (
        // oldVNode may be an errored async setup() component inside Suspense
        // which will not have a mounted element
        v.el && // - In the case of a Fragment, we need to provide the actual parent
        // of the Fragment itself so it can move its children.
        (v.type === ne || // - In the case of different nodes, there is going to be a replacement
        // which also requires the correct parent container
        !yt(v, m) || // - In the case of a component, it could contain anything.
        v.shapeFlag & 198) ? p(v.el) : (
          // In other cases, the parent container is not actually used so we
          // just pass the block element here to avoid a DOM parentNode call.
          h
        )
      );
      M(
        v,
        m,
        E,
        null,
        b,
        g,
        _,
        x,
        !0
      );
    }
  }, gt = (f, a, h, b, g) => {
    if (a !== h) {
      if (a !== U)
        for (const _ in a)
          !St(_) && !(_ in h) && i(
            f,
            _,
            a[_],
            null,
            g,
            b
          );
      for (const _ in h) {
        if (St(_)) continue;
        const x = h[_], y = a[_];
        x !== y && _ !== "value" && i(f, _, y, x, g, b);
      }
      "value" in h && i(f, "value", a.value, h.value, g);
    }
  }, Ht = (f, a, h, b, g, _, x, y, v) => {
    const m = a.el = f ? f.el : o(""), E = a.anchor = f ? f.anchor : o("");
    let { patchFlag: S, dynamicChildren: T, slotScopeIds: A } = a;
    A && (y = y ? y.concat(A) : A), f == null ? (n(m, h, b), n(E, h, b), Ge(
      // #10007
      // such fragment like `<></>` will be compiled into
      // a fragment which doesn't have a children.
      // In this case fallback to an empty array
      a.children || [],
      h,
      E,
      g,
      _,
      x,
      y,
      v
    )) : S > 0 && S & 64 && T && // #2715 the previous fragment could've been a BAILed one as a result
    // of renderSlot() with no valid children
    f.dynamicChildren && f.dynamicChildren.length === T.length ? (ze(
      f.dynamicChildren,
      T,
      h,
      g,
      _,
      x,
      y
    ), // #2080 if the stable fragment has a key, it's a <template v-for> that may
    //  get moved around. Make sure all root level vnodes inherit el.
    // #2134 or if it's a component root, it may also get moved around
    // as the component is being moved.
    (a.key != null || g && a === g.subTree) && Jr(
      f,
      a,
      !0
      /* shallow */
    )) : W(
      f,
      a,
      h,
      E,
      g,
      _,
      x,
      y,
      v
    );
  }, kt = (f, a, h, b, g, _, x, y, v) => {
    a.slotScopeIds = y, f == null ? a.shapeFlag & 512 ? g.ctx.activate(
      a,
      h,
      b,
      x,
      v
    ) : gs(
      a,
      h,
      b,
      g,
      _,
      x,
      v
    ) : dn(f, a, v);
  }, gs = (f, a, h, b, g, _, x) => {
    const y = f.component = ro(
      f,
      b,
      g
    );
    if (Rr(f) && (y.ctx.renderer = mt), lo(y, !1, x), y.asyncDep) {
      if (g && g.registerDep(y, oe, x), !f.el) {
        const v = y.subTree = me(Je);
        q(null, v, a, h), f.placeholder = v.el;
      }
    } else
      oe(
        y,
        f,
        a,
        h,
        g,
        _,
        x
      );
  }, dn = (f, a, h) => {
    const b = a.component = f.component;
    if (kl(f, a, h))
      if (b.asyncDep && !b.asyncResolved) {
        J(b, a, h);
        return;
      } else
        b.next = a, b.update();
    else
      a.el = f.el, b.vnode = a;
  }, oe = (f, a, h, b, g, _, x) => {
    const y = () => {
      if (f.isMounted) {
        let { next: S, bu: T, u: A, parent: $, vnode: V } = f;
        {
          const Ce = zr(f);
          if (Ce) {
            S && (S.el = V.el, J(f, S, x)), Ce.asyncDep.then(() => {
              de(() => {
                f.isUnmounted || m();
              }, g);
            });
            return;
          }
        }
        let B = S, X;
        Qe(f, !1), S ? (S.el = V.el, J(f, S, x)) : S = V, T && Kt(T), (X = S.props && S.props.onVnodeBeforeUpdate) && Ee(X, $, S, V), Qe(f, !0);
        const te = Mn(f), we = f.subTree;
        f.subTree = te, M(
          we,
          te,
          // parent may have changed if it's in a teleport
          p(we.el),
          // anchor may have changed if it's in a fragment
          Vt(we),
          f,
          g,
          _
        ), S.el = te.el, B === null && Ll(f, te.el), A && de(A, g), (X = S.props && S.props.onVnodeUpdated) && de(
          () => Ee(X, $, S, V),
          g
        );
      } else {
        let S;
        const { el: T, props: A } = a, { bm: $, m: V, parent: B, root: X, type: te } = f, we = Et(a);
        Qe(f, !1), $ && Kt($), !we && (S = A && A.onVnodeBeforeMount) && Ee(S, B, a), Qe(f, !0);
        {
          X.ce && X.ce._hasShadowRoot() && X.ce._injectChildStyle(
            te,
            f.parent ? f.parent.type : void 0
          );
          const Ce = f.subTree = Mn(f);
          M(
            null,
            Ce,
            h,
            b,
            f,
            g,
            _
          ), a.el = Ce.el;
        }
        if (V && de(V, g), !we && (S = A && A.onVnodeMounted)) {
          const Ce = a;
          de(
            () => Ee(S, B, Ce),
            g
          );
        }
        (a.shapeFlag & 256 || B && Et(B.vnode) && B.vnode.shapeFlag & 256) && f.a && de(f.a, g), f.isMounted = !0, a = h = b = null;
      }
    };
    f.scope.on();
    const v = f.effect = new cr(y);
    f.scope.off();
    const m = f.update = v.run.bind(v), E = f.job = v.runIfDirty.bind(v);
    E.i = f, E.id = f.uid, v.scheduler = () => ln(E), Qe(f, !0), m();
  }, J = (f, a, h) => {
    a.component = f;
    const b = f.vnode.props;
    f.vnode = a, f.next = null, Ul(f, a.props, b, h), Gl(f, a.children, h), Le(), Sn(f), Ve();
  }, W = (f, a, h, b, g, _, x, y, v = !1) => {
    const m = f && f.children, E = f ? f.shapeFlag : 0, S = a.children, { patchFlag: T, shapeFlag: A } = a;
    if (T > 0) {
      if (T & 128) {
        Lt(
          m,
          S,
          h,
          b,
          g,
          _,
          x,
          y,
          v
        );
        return;
      } else if (T & 256) {
        Ye(
          m,
          S,
          h,
          b,
          g,
          _,
          x,
          y,
          v
        );
        return;
      }
    }
    A & 8 ? (E & 16 && _t(m, g, _), S !== m && u(h, S)) : E & 16 ? A & 16 ? Lt(
      m,
      S,
      h,
      b,
      g,
      _,
      x,
      y,
      v
    ) : _t(m, g, _, !0) : (E & 8 && u(h, ""), A & 16 && Ge(
      S,
      h,
      b,
      g,
      _,
      x,
      y,
      v
    ));
  }, Ye = (f, a, h, b, g, _, x, y, v) => {
    f = f || ft, a = a || ft;
    const m = f.length, E = a.length, S = Math.min(m, E);
    let T;
    for (T = 0; T < S; T++) {
      const A = a[T] = v ? je(a[T]) : Oe(a[T]);
      M(
        f[T],
        A,
        h,
        null,
        g,
        _,
        x,
        y,
        v
      );
    }
    m > E ? _t(
      f,
      g,
      _,
      !0,
      !1,
      S
    ) : Ge(
      a,
      h,
      b,
      g,
      _,
      x,
      y,
      v,
      S
    );
  }, Lt = (f, a, h, b, g, _, x, y, v) => {
    let m = 0;
    const E = a.length;
    let S = f.length - 1, T = E - 1;
    for (; m <= S && m <= T; ) {
      const A = f[m], $ = a[m] = v ? je(a[m]) : Oe(a[m]);
      if (yt(A, $))
        M(
          A,
          $,
          h,
          null,
          g,
          _,
          x,
          y,
          v
        );
      else
        break;
      m++;
    }
    for (; m <= S && m <= T; ) {
      const A = f[S], $ = a[T] = v ? je(a[T]) : Oe(a[T]);
      if (yt(A, $))
        M(
          A,
          $,
          h,
          null,
          g,
          _,
          x,
          y,
          v
        );
      else
        break;
      S--, T--;
    }
    if (m > S) {
      if (m <= T) {
        const A = T + 1, $ = A < E ? a[A].el : b;
        for (; m <= T; )
          M(
            null,
            a[m] = v ? je(a[m]) : Oe(a[m]),
            h,
            $,
            g,
            _,
            x,
            y,
            v
          ), m++;
      }
    } else if (m > T)
      for (; m <= S; )
        Se(f[m], g, _, !0), m++;
    else {
      const A = m, $ = m, V = /* @__PURE__ */ new Map();
      for (m = $; m <= T; m++) {
        const pe = a[m] = v ? je(a[m]) : Oe(a[m]);
        pe.key != null && V.set(pe.key, m);
      }
      let B, X = 0;
      const te = T - $ + 1;
      let we = !1, Ce = 0;
      const bt = new Array(te);
      for (m = 0; m < te; m++) bt[m] = 0;
      for (m = A; m <= S; m++) {
        const pe = f[m];
        if (X >= te) {
          Se(pe, g, _, !0);
          continue;
        }
        let Te;
        if (pe.key != null)
          Te = V.get(pe.key);
        else
          for (B = $; B <= T; B++)
            if (bt[B - $] === 0 && yt(pe, a[B])) {
              Te = B;
              break;
            }
        Te === void 0 ? Se(pe, g, _, !0) : (bt[Te - $] = m + 1, Te >= Ce ? Ce = Te : we = !0, M(
          pe,
          a[Te],
          h,
          null,
          g,
          _,
          x,
          y,
          v
        ), X++);
      }
      const gn = we ? Yl(bt) : ft;
      for (B = gn.length - 1, m = te - 1; m >= 0; m--) {
        const pe = $ + m, Te = a[pe], _n = a[pe + 1], mn = pe + 1 < E ? (
          // #13559, #14173 fallback to el placeholder for unresolved async component
          _n.el || Yr(_n)
        ) : b;
        bt[m] === 0 ? M(
          null,
          Te,
          h,
          mn,
          g,
          _,
          x,
          y,
          v
        ) : we && (B < 0 || m !== gn[B] ? Xe(Te, h, mn, 2) : B--);
      }
    }
  }, Xe = (f, a, h, b, g = null) => {
    const { el: _, type: x, transition: y, children: v, shapeFlag: m } = f;
    if (m & 6) {
      Xe(f.component.subTree, a, h, b);
      return;
    }
    if (m & 128) {
      f.suspense.move(a, h, b);
      return;
    }
    if (m & 64) {
      x.move(f, a, h, mt);
      return;
    }
    if (x === ne) {
      n(_, a, h);
      for (let S = 0; S < v.length; S++)
        Xe(v[S], a, h, b);
      n(f.anchor, a, h);
      return;
    }
    if (x === Ts) {
      G(f, a, h);
      return;
    }
    if (b !== 2 && m & 1 && y)
      if (b === 0)
        y.beforeEnter(_), n(_, a, h), de(() => y.enter(_), g);
      else {
        const { leave: S, delayLeave: T, afterLeave: A } = y, $ = () => {
          f.ctx.isUnmounted ? r(_) : n(_, a, h);
        }, V = () => {
          _._isLeaving && _[ul](
            !0
            /* cancelled */
          ), S(_, () => {
            $(), A && A();
          });
        };
        T ? T(_, $, V) : V();
      }
    else
      n(_, a, h);
  }, Se = (f, a, h, b = !1, g = !1) => {
    const {
      type: _,
      props: x,
      ref: y,
      children: v,
      dynamicChildren: m,
      shapeFlag: E,
      patchFlag: S,
      dirs: T,
      cacheIndex: A,
      memo: $
    } = f;
    if (S === -2 && (g = !1), y != null && (Le(), Tt(y, null, h, f, !0), Ve()), A != null && (a.renderCache[A] = void 0), E & 256) {
      a.ctx.deactivate(f);
      return;
    }
    const V = E & 1 && T, B = !Et(f);
    let X;
    if (B && (X = x && x.onVnodeBeforeUnmount) && Ee(X, a, f), E & 6)
      ui(f.component, h, b);
    else {
      if (E & 128) {
        f.suspense.unmount(h, b);
        return;
      }
      V && Ze(f, null, a, "beforeUnmount"), E & 64 ? f.type.remove(
        f,
        a,
        h,
        mt,
        b
      ) : m && // #5154
      // when v-once is used inside a block, setBlockTracking(-1) marks the
      // parent block with hasOnce: true
      // so that it doesn't take the fast path during unmount - otherwise
      // components nested in v-once are never unmounted.
      !m.hasOnce && // #1153: fast path should not be taken for non-stable (v-for) fragments
      (_ !== ne || S > 0 && S & 64) ? _t(
        m,
        a,
        h,
        !1,
        !0
      ) : (_ === ne && S & 384 || !g && E & 16) && _t(v, a, h), b && hn(f);
    }
    const te = $ != null && A == null;
    (B && (X = x && x.onVnodeUnmounted) || V || te) && de(() => {
      X && Ee(X, a, f), V && Ze(f, null, a, "unmounted"), te && (f.el = null);
    }, h);
  }, hn = (f) => {
    const { type: a, el: h, anchor: b, transition: g } = f;
    if (a === ne) {
      fi(h, b);
      return;
    }
    if (a === Ts) {
      O(f);
      return;
    }
    const _ = () => {
      r(h), g && !g.persisted && g.afterLeave && g.afterLeave();
    };
    if (f.shapeFlag & 1 && g && !g.persisted) {
      const { leave: x, delayLeave: y } = g, v = () => x(h, _);
      y ? y(f.el, _, v) : v();
    } else
      _();
  }, fi = (f, a) => {
    let h;
    for (; f !== a; )
      h = w(f), r(f), f = h;
    r(a);
  }, ui = (f, a, h) => {
    const { bum: b, scope: g, job: _, subTree: x, um: y, m: v, a: m } = f;
    $n(v), $n(m), b && Kt(b), g.stop(), _ && (_.flags |= 8, Se(x, f, a, h)), y && de(y, a), de(() => {
      f.isUnmounted = !0;
    }, a);
  }, _t = (f, a, h, b = !1, g = !1, _ = 0) => {
    for (let x = _; x < f.length; x++)
      Se(f[x], a, h, b, g);
  }, Vt = (f) => {
    if (f.shapeFlag & 6)
      return Vt(f.component.subTree);
    if (f.shapeFlag & 128)
      return f.suspense.next();
    const a = w(f.anchor || f.el), h = a && a[cl];
    return h ? w(h) : a;
  };
  let _s = !1;
  const pn = (f, a, h) => {
    let b;
    f == null ? a._vnode && (Se(a._vnode, null, null, !0), b = a._vnode.component) : M(
      a._vnode || null,
      f,
      a,
      null,
      null,
      null,
      h
    ), a._vnode = f, _s || (_s = !0, Sn(b), Tr(), _s = !1);
  }, mt = {
    p: M,
    um: Se,
    m: Xe,
    r: hn,
    mt: gs,
    mc: Ge,
    pc: W,
    pbc: ze,
    n: Vt,
    o: e
  };
  return {
    render: pn,
    hydrate: void 0,
    createApp: $l(pn)
  };
}
function Cs({ type: e, props: t }, s) {
  return s === "svg" && e === "foreignObject" || s === "mathml" && e === "annotation-xml" && t && t.encoding && t.encoding.includes("html") ? void 0 : s;
}
function Qe({ effect: e, job: t }, s) {
  s ? (e.flags |= 32, t.flags |= 4) : (e.flags &= -33, t.flags &= -5);
}
function zl(e, t) {
  return (!e || e && !e.pendingBranch) && t && !t.persisted;
}
function Jr(e, t, s = !1) {
  const n = e.children, r = t.children;
  if (P(n) && P(r))
    for (let i = 0; i < n.length; i++) {
      const l = n[i];
      let o = r[i];
      o.shapeFlag & 1 && !o.dynamicChildren && ((o.patchFlag <= 0 || o.patchFlag === 32) && (o = r[i] = je(r[i]), o.el = l.el), !s && o.patchFlag !== -2 && Jr(l, o)), o.type === hs && (o.patchFlag === -1 && (o = r[i] = je(o)), o.el = l.el), o.type === Je && !o.el && (o.el = l.el);
    }
}
function Yl(e) {
  const t = e.slice(), s = [0];
  let n, r, i, l, o;
  const c = e.length;
  for (n = 0; n < c; n++) {
    const d = e[n];
    if (d !== 0) {
      if (r = s[s.length - 1], e[r] < d) {
        t[n] = r, s.push(n);
        continue;
      }
      for (i = 0, l = s.length - 1; i < l; )
        o = i + l >> 1, e[s[o]] < d ? i = o + 1 : l = o;
      d < e[s[i]] && (i > 0 && (t[n] = s[i - 1]), s[i] = n);
    }
  }
  for (i = s.length, l = s[i - 1]; i-- > 0; )
    s[i] = l, l = t[l];
  return s;
}
function zr(e) {
  const t = e.subTree.component;
  if (t)
    return t.asyncDep && !t.asyncResolved ? t : zr(t);
}
function $n(e) {
  if (e)
    for (let t = 0; t < e.length; t++)
      e[t].flags |= 8;
}
function Yr(e) {
  if (e.placeholder)
    return e.placeholder;
  const t = e.component;
  return t ? Yr(t.subTree) : null;
}
const Xr = (e) => e.__isSuspense;
function Xl(e, t) {
  t && t.pendingBranch ? P(e) ? t.effects.push(...e) : t.effects.push(e) : tl(e);
}
const ne = /* @__PURE__ */ Symbol.for("v-fgt"), hs = /* @__PURE__ */ Symbol.for("v-txt"), Je = /* @__PURE__ */ Symbol.for("v-cmt"), Ts = /* @__PURE__ */ Symbol.for("v-stc"), Pt = [];
let _e = null;
function j(e = !1) {
  Pt.push(_e = e ? null : []);
}
function Zl() {
  Pt.pop(), _e = Pt[Pt.length - 1] || null;
}
let It = 1;
function es(e, t = !1) {
  It += e, e < 0 && _e && t && (_e.hasOnce = !0);
}
function Zr(e) {
  return e.dynamicChildren = It > 0 ? _e || ft : null, Zl(), It > 0 && _e && _e.push(e), e;
}
function L(e, t, s, n, r, i) {
  return Zr(
    N(
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
function Qr(e, t, s, n, r) {
  return Zr(
    me(
      e,
      t,
      s,
      n,
      r,
      !0
    )
  );
}
function ts(e) {
  return e ? e.__v_isVNode === !0 : !1;
}
function yt(e, t) {
  return e.type === t.type && e.key === t.key;
}
const ei = ({ key: e }) => e ?? null, qt = ({
  ref: e,
  ref_key: t,
  ref_for: s
}) => (typeof e == "number" && (e = "" + e), e != null ? z(e) || /* @__PURE__ */ ie(e) || R(e) ? { i: ge, r: e, k: t, f: !!s } : e : null);
function N(e, t = null, s = null, n = 0, r = null, i = e === ne ? 0 : 1, l = !1, o = !1) {
  const c = {
    __v_isVNode: !0,
    __v_skip: !0,
    type: e,
    props: t,
    key: t && ei(t),
    ref: t && qt(t),
    scopeId: Ar,
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
    ctx: ge
  };
  return o ? (an(c, s), i & 128 && e.normalize(c)) : s && (c.shapeFlag |= z(s) ? 8 : 16), It > 0 && // avoid a block node from tracking itself
  !l && // has current parent block
  _e && // presence of a patch flag indicates this node needs patching on updates.
  // component nodes also should always be patched, because even if the
  // component doesn't need to update, it needs to persist the instance on to
  // the next vnode so that it can be properly unmounted later.
  (c.patchFlag > 0 || i & 6) && // the EVENTS flag is only for hydration and if it is the only flag, the
  // vnode should not be considered dynamic due to handler caching.
  c.patchFlag !== 32 && _e.push(c), c;
}
const me = Ql;
function Ql(e, t = null, s = null, n = 0, r = null, i = !1) {
  if ((!e || e === Fr) && (e = Je), ts(e)) {
    const o = pt(
      e,
      t,
      !0
      /* mergeRef: true */
    );
    return s && an(o, s), It > 0 && !i && _e && (o.shapeFlag & 6 ? _e[_e.indexOf(e)] = o : _e.push(o)), o.patchFlag = -2, o;
  }
  if (ao(e) && (e = e.__vccOpts), t) {
    t = eo(t);
    let { class: o, style: c } = t;
    o && !z(o) && (t.class = it(o)), H(c) && (/* @__PURE__ */ rn(c) && !P(c) && (c = le({}, c)), t.style = Ys(c));
  }
  const l = z(e) ? 1 : Xr(e) ? 128 : fl(e) ? 64 : H(e) ? 4 : R(e) ? 2 : 0;
  return N(
    e,
    t,
    s,
    n,
    r,
    l,
    i,
    !0
  );
}
function eo(e) {
  return e ? /* @__PURE__ */ rn(e) || Ur(e) ? le({}, e) : e : null;
}
function pt(e, t, s = !1, n = !1) {
  const { props: r, ref: i, patchFlag: l, children: o, transition: c } = e, d = t ? to(r || {}, t) : r, u = {
    __v_isVNode: !0,
    __v_skip: !0,
    type: e.type,
    props: d,
    key: d && ei(d),
    ref: t && t.ref ? (
      // #2078 in the case of <component :is="vnode" ref="extra"/>
      // if the vnode itself already has a ref, cloneVNode will need to merge
      // the refs so the single vnode can be set on multiple refs
      s && i ? P(i) ? i.concat(qt(t)) : [i, qt(t)] : qt(t)
    ) : i,
    scopeId: e.scopeId,
    slotScopeIds: e.slotScopeIds,
    children: o,
    target: e.target,
    targetStart: e.targetStart,
    targetAnchor: e.targetAnchor,
    staticCount: e.staticCount,
    shapeFlag: e.shapeFlag,
    // if the vnode is cloned with extra props, we can no longer assume its
    // existing patch flag to be reliable and need to add the FULL_PROPS flag.
    // note: preserve flag for fragments since they use the flag for children
    // fast paths only.
    patchFlag: t && e.type !== ne ? l === -1 ? 16 : l | 16 : l,
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
    ssContent: e.ssContent && pt(e.ssContent),
    ssFallback: e.ssFallback && pt(e.ssFallback),
    placeholder: e.placeholder,
    el: e.el,
    anchor: e.anchor,
    ctx: e.ctx,
    ce: e.ce
  };
  return c && n && on(
    u,
    c.clone(u)
  ), u;
}
function un(e = " ", t = 0) {
  return me(hs, null, e, t);
}
function Re(e = "", t = !1) {
  return t ? (j(), Qr(Je, null, e)) : me(Je, null, e);
}
function Oe(e) {
  return e == null || typeof e == "boolean" ? me(Je) : P(e) ? me(
    ne,
    null,
    // #3666, avoid reference pollution when reusing vnode
    e.slice()
  ) : ts(e) ? je(e) : me(hs, null, String(e));
}
function je(e) {
  return e.el === null && e.patchFlag !== -1 || e.memo ? e : pt(e);
}
function an(e, t) {
  let s = 0;
  const { shapeFlag: n } = e;
  if (t == null)
    t = null;
  else if (P(t))
    s = 16;
  else if (typeof t == "object")
    if (n & 65) {
      const r = t.default;
      r && (r._c && (r._d = !1), an(e, r()), r._c && (r._d = !0));
      return;
    } else {
      s = 32;
      const r = t._;
      !r && !Ur(t) ? t._ctx = ge : r === 3 && ge && (ge.slots._ === 1 ? t._ = 1 : (t._ = 2, e.patchFlag |= 1024));
    }
  else R(t) ? (t = { default: t, _ctx: ge }, s = 32) : (t = String(t), n & 64 ? (s = 16, t = [un(t)]) : s = 8);
  e.children = t, e.shapeFlag |= s;
}
function to(...e) {
  const t = {};
  for (let s = 0; s < e.length; s++) {
    const n = e[s];
    for (const r in n)
      if (r === "class")
        t.class !== n.class && (t.class = it([t.class, n.class]));
      else if (r === "style")
        t.style = Ys([t.style, n.style]);
      else if (ns(r)) {
        const i = t[r], l = n[r];
        l && i !== l && !(P(i) && i.includes(l)) ? t[r] = i ? [].concat(i, l) : l : l == null && i == null && // mergeProps({ 'onUpdate:modelValue': undefined }) should not retain
        // the model listener.
        !rs(r) && (t[r] = l);
      } else r !== "" && (t[r] = n[r]);
  }
  return t;
}
function Ee(e, t, s, n = null) {
  Fe(e, t, 7, [
    s,
    n
  ]);
}
const so = jr();
let no = 0;
function ro(e, t, s) {
  const n = e.type, r = (t ? t.appContext : e.appContext) || so, i = {
    uid: no++,
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
    scope: new Si(
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
    propsOptions: Br(n, r),
    emitsOptions: Hr(n, r),
    // emit
    emit: null,
    // to be set immediately
    emitted: null,
    // props default value
    propsDefaults: U,
    // inheritAttrs
    inheritAttrs: n.inheritAttrs,
    // state
    ctx: U,
    data: U,
    props: U,
    attrs: U,
    slots: U,
    refs: U,
    setupState: U,
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
  return i.ctx = { _: i }, i.root = t ? t.root : i, i.emit = Dl.bind(null, i), e.ce && e.ce(i), i;
}
let re = null;
const io = () => re || ge;
let ss, ks;
{
  const e = os(), t = (s, n) => {
    let r;
    return (r = e[s]) || (r = e[s] = []), r.push(n), (i) => {
      r.length > 1 ? r.forEach((l) => l(i)) : r[0](i);
    };
  };
  ss = t(
    "__VUE_INSTANCE_SETTERS__",
    (s) => re = s
  ), ks = t(
    "__VUE_SSR_SETTERS__",
    (s) => $t = s
  );
}
const Nt = (e) => {
  const t = re;
  return ss(e), e.scope.on(), () => {
    e.scope.off(), ss(t);
  };
}, Fn = () => {
  re && re.scope.off(), ss(null);
};
function ti(e) {
  return e.vnode.shapeFlag & 4;
}
let $t = !1;
function lo(e, t = !1, s = !1) {
  t && ks(t);
  const { props: n, children: r } = e.vnode, i = ti(e);
  Vl(e, n, i, t), Kl(e, r, s || t);
  const l = i ? oo(e, t) : void 0;
  return t && ks(!1), l;
}
function oo(e, t) {
  const s = e.type;
  e.accessCache = /* @__PURE__ */ Object.create(null), e.proxy = new Proxy(e.ctx, El);
  const { setup: n } = s;
  if (n) {
    Le();
    const r = e.setupContext = n.length > 1 ? fo(e) : null, i = Nt(e), l = Dt(
      n,
      e,
      0,
      [
        e.props,
        r
      ]
    ), o = tr(l);
    if (Ve(), i(), (o || e.sp) && !Et(e) && Mr(e), o) {
      if (l.then(Fn, Fn), t)
        return l.then((c) => {
          Dn(e, c);
        }).catch((c) => {
          us(c, e, 0);
        });
      e.asyncDep = l;
    } else
      Dn(e, l);
  } else
    si(e);
}
function Dn(e, t, s) {
  R(t) ? e.type.__ssrInlineRender ? e.ssrRender = t : e.render = t : H(t) && (e.setupState = Sr(t)), si(e);
}
function si(e, t, s) {
  const n = e.type;
  e.render || (e.render = n.render || Ie);
  {
    const r = Nt(e);
    Le();
    try {
      Al(e);
    } finally {
      Ve(), r();
    }
  }
}
const co = {
  get(e, t) {
    return se(e, "get", ""), e[t];
  }
};
function fo(e) {
  const t = (s) => {
    e.exposed = s || {};
  };
  return {
    attrs: new Proxy(e.attrs, co),
    slots: e.slots,
    emit: e.emit,
    expose: t
  };
}
function ps(e) {
  return e.exposed ? e.exposeProxy || (e.exposeProxy = new Proxy(Sr(Wi(e.exposed)), {
    get(t, s) {
      if (s in t)
        return t[s];
      if (s in At)
        return At[s](e);
    },
    has(t, s) {
      return s in t || s in At;
    }
  })) : e.proxy;
}
function uo(e, t = !0) {
  return R(e) ? e.displayName || e.name : e.name || t && e.__name;
}
function ao(e) {
  return R(e) && "__vccOpts" in e;
}
const ue = (e, t) => /* @__PURE__ */ zi(e, t, $t);
function ho(e, t, s) {
  try {
    es(-1);
    const n = arguments.length;
    return n === 2 ? H(t) && !P(t) ? ts(t) ? me(e, null, [t]) : me(e, t) : me(e, null, t) : (n > 3 ? s = Array.prototype.slice.call(arguments, 2) : n === 3 && ts(s) && (s = [s]), me(e, t, s));
  } finally {
    es(1);
  }
}
const po = "3.5.31";
/**
* @vue/runtime-dom v3.5.31
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
let Ls;
const Nn = typeof window < "u" && window.trustedTypes;
if (Nn)
  try {
    Ls = /* @__PURE__ */ Nn.createPolicy("vue", {
      createHTML: (e) => e
    });
  } catch {
  }
const ni = Ls ? (e) => Ls.createHTML(e) : (e) => e, go = "http://www.w3.org/2000/svg", _o = "http://www.w3.org/1998/Math/MathML", Ne = typeof document < "u" ? document : null, jn = Ne && /* @__PURE__ */ Ne.createElement("template"), mo = {
  insert: (e, t, s) => {
    t.insertBefore(e, s || null);
  },
  remove: (e) => {
    const t = e.parentNode;
    t && t.removeChild(e);
  },
  createElement: (e, t, s, n) => {
    const r = t === "svg" ? Ne.createElementNS(go, e) : t === "mathml" ? Ne.createElementNS(_o, e) : s ? Ne.createElement(e, { is: s }) : Ne.createElement(e);
    return e === "select" && n && n.multiple != null && r.setAttribute("multiple", n.multiple), r;
  },
  createText: (e) => Ne.createTextNode(e),
  createComment: (e) => Ne.createComment(e),
  setText: (e, t) => {
    e.nodeValue = t;
  },
  setElementText: (e, t) => {
    e.textContent = t;
  },
  parentNode: (e) => e.parentNode,
  nextSibling: (e) => e.nextSibling,
  querySelector: (e) => Ne.querySelector(e),
  setScopeId(e, t) {
    e.setAttribute(t, "");
  },
  // __UNSAFE__
  // Reason: innerHTML.
  // Static content here can only come from compiled templates.
  // As long as the user only uses trusted templates, this is safe.
  insertStaticContent(e, t, s, n, r, i) {
    const l = s ? s.previousSibling : t.lastChild;
    if (r && (r === i || r.nextSibling))
      for (; t.insertBefore(r.cloneNode(!0), s), !(r === i || !(r = r.nextSibling)); )
        ;
    else {
      jn.innerHTML = ni(
        n === "svg" ? `<svg>${e}</svg>` : n === "mathml" ? `<math>${e}</math>` : e
      );
      const o = jn.content;
      if (n === "svg" || n === "mathml") {
        const c = o.firstChild;
        for (; c.firstChild; )
          o.appendChild(c.firstChild);
        o.removeChild(c);
      }
      t.insertBefore(o, s);
    }
    return [
      // first
      l ? l.nextSibling : t.firstChild,
      // last
      s ? s.previousSibling : t.lastChild
    ];
  }
}, bo = /* @__PURE__ */ Symbol("_vtc");
function vo(e, t, s) {
  const n = e[bo];
  n && (t = (t ? [t, ...n] : [...n]).join(" ")), t == null ? e.removeAttribute("class") : s ? e.setAttribute("class", t) : e.className = t;
}
const Hn = /* @__PURE__ */ Symbol("_vod"), yo = /* @__PURE__ */ Symbol("_vsh"), xo = /* @__PURE__ */ Symbol(""), So = /(?:^|;)\s*display\s*:/;
function wo(e, t, s) {
  const n = e.style, r = z(s);
  let i = !1;
  if (s && !r) {
    if (t)
      if (z(t))
        for (const l of t.split(";")) {
          const o = l.slice(0, l.indexOf(":")).trim();
          s[o] == null && Jt(n, o, "");
        }
      else
        for (const l in t)
          s[l] == null && Jt(n, l, "");
    for (const l in s)
      l === "display" && (i = !0), Jt(n, l, s[l]);
  } else if (r) {
    if (t !== s) {
      const l = n[xo];
      l && (s += ";" + l), n.cssText = s, i = So.test(s);
    }
  } else t && e.removeAttribute("style");
  Hn in e && (e[Hn] = i ? n.display : "", e[yo] && (n.display = "none"));
}
const kn = /\s*!important$/;
function Jt(e, t, s) {
  if (P(s))
    s.forEach((n) => Jt(e, t, n));
  else if (s == null && (s = ""), t.startsWith("--"))
    e.setProperty(t, s);
  else {
    const n = Co(e, t);
    kn.test(s) ? e.setProperty(
      rt(n),
      s.replace(kn, ""),
      "important"
    ) : e[n] = s;
  }
}
const Ln = ["Webkit", "Moz", "ms"], Es = {};
function Co(e, t) {
  const s = Es[t];
  if (s)
    return s;
  let n = ae(t);
  if (n !== "filter" && n in e)
    return Es[t] = n;
  n = ls(n);
  for (let r = 0; r < Ln.length; r++) {
    const i = Ln[r] + n;
    if (i in e)
      return Es[t] = i;
  }
  return t;
}
const Vn = "http://www.w3.org/1999/xlink";
function Un(e, t, s, n, r, i = yi(t)) {
  n && t.startsWith("xlink:") ? s == null ? e.removeAttributeNS(Vn, t.slice(6, t.length)) : e.setAttributeNS(Vn, t, s) : s == null || i && !ir(s) ? e.removeAttribute(t) : e.setAttribute(
    t,
    i ? "" : $e(s) ? String(s) : s
  );
}
function Wn(e, t, s, n, r) {
  if (t === "innerHTML" || t === "textContent") {
    s != null && (e[t] = t === "innerHTML" ? ni(s) : s);
    return;
  }
  const i = e.tagName;
  if (t === "value" && i !== "PROGRESS" && // custom elements may use _value internally
  !i.includes("-")) {
    const o = i === "OPTION" ? e.getAttribute("value") || "" : e.value, c = s == null ? (
      // #11647: value should be set as empty string for null and undefined,
      // but <input type="checkbox"> should be set as 'on'.
      e.type === "checkbox" ? "on" : ""
    ) : String(s);
    (o !== c || !("_value" in e)) && (e.value = c), s == null && e.removeAttribute(t), e._value = s;
    return;
  }
  let l = !1;
  if (s === "" || s == null) {
    const o = typeof e[t];
    o === "boolean" ? s = ir(s) : s == null && o === "string" ? (s = "", l = !0) : o === "number" && (s = 0, l = !0);
  }
  try {
    e[t] = s;
  } catch {
  }
  l && e.removeAttribute(r || t);
}
function ct(e, t, s, n) {
  e.addEventListener(t, s, n);
}
function To(e, t, s, n) {
  e.removeEventListener(t, s, n);
}
const Bn = /* @__PURE__ */ Symbol("_vei");
function Eo(e, t, s, n, r = null) {
  const i = e[Bn] || (e[Bn] = {}), l = i[t];
  if (n && l)
    l.value = n;
  else {
    const [o, c] = Ao(t);
    if (n) {
      const d = i[t] = Mo(
        n,
        r
      );
      ct(e, o, d, c);
    } else l && (To(e, o, l, c), i[t] = void 0);
  }
}
const Kn = /(?:Once|Passive|Capture)$/;
function Ao(e) {
  let t;
  if (Kn.test(e)) {
    t = {};
    let n;
    for (; n = e.match(Kn); )
      e = e.slice(0, e.length - n[0].length), t[n[0].toLowerCase()] = !0;
  }
  return [e[2] === ":" ? e.slice(3) : rt(e.slice(2)), t];
}
let As = 0;
const Po = /* @__PURE__ */ Promise.resolve(), Oo = () => As || (Po.then(() => As = 0), As = Date.now());
function Mo(e, t) {
  const s = (n) => {
    if (!n._vts)
      n._vts = Date.now();
    else if (n._vts <= s.attached)
      return;
    Fe(
      Ro(n, s.value),
      t,
      5,
      [n]
    );
  };
  return s.value = e, s.attached = Oo(), s;
}
function Ro(e, t) {
  if (P(t)) {
    const s = e.stopImmediatePropagation;
    return e.stopImmediatePropagation = () => {
      s.call(e), e._stopped = !0;
    }, t.map(
      (n) => (r) => !r._stopped && n && n(r)
    );
  } else
    return t;
}
const Gn = (e) => e.charCodeAt(0) === 111 && e.charCodeAt(1) === 110 && // lowercase letter
e.charCodeAt(2) > 96 && e.charCodeAt(2) < 123, Io = (e, t, s, n, r, i) => {
  const l = r === "svg";
  t === "class" ? vo(e, n, l) : t === "style" ? wo(e, s, n) : ns(t) ? rs(t) || Eo(e, t, s, n, i) : (t[0] === "." ? (t = t.slice(1), !0) : t[0] === "^" ? (t = t.slice(1), !1) : $o(e, t, n, l)) ? (Wn(e, t, n), !e.tagName.includes("-") && (t === "value" || t === "checked" || t === "selected") && Un(e, t, n, l, i, t !== "value")) : /* #11081 force set props for possible async custom element */ e._isVueCE && // #12408 check if it's declared prop or it's async custom element
  (Fo(e, t) || // @ts-expect-error _def is private
  e._def.__asyncLoader && (/[A-Z]/.test(t) || !z(n))) ? Wn(e, ae(t), n, i, t) : (t === "true-value" ? e._trueValue = n : t === "false-value" && (e._falseValue = n), Un(e, t, n, l));
};
function $o(e, t, s, n) {
  if (n)
    return !!(t === "innerHTML" || t === "textContent" || t in e && Gn(t) && R(s));
  if (t === "spellcheck" || t === "draggable" || t === "translate" || t === "autocorrect" || t === "sandbox" && e.tagName === "IFRAME" || t === "form" || t === "list" && e.tagName === "INPUT" || t === "type" && e.tagName === "TEXTAREA")
    return !1;
  if (t === "width" || t === "height") {
    const r = e.tagName;
    if (r === "IMG" || r === "VIDEO" || r === "CANVAS" || r === "SOURCE")
      return !1;
  }
  return Gn(t) && z(s) ? !1 : t in e;
}
function Fo(e, t) {
  const s = (
    // @ts-expect-error _def is private
    e._def.props
  );
  if (!s)
    return !1;
  const n = ae(t);
  return Array.isArray(s) ? s.some((r) => ae(r) === n) : Object.keys(s).some((r) => ae(r) === n);
}
const qn = (e) => {
  const t = e.props["onUpdate:modelValue"] || !1;
  return P(t) ? (s) => Kt(t, s) : t;
};
function Do(e) {
  e.target.composing = !0;
}
function Jn(e) {
  const t = e.target;
  t.composing && (t.composing = !1, t.dispatchEvent(new Event("input")));
}
const Ps = /* @__PURE__ */ Symbol("_assign");
function zn(e, t, s) {
  return t && (e = e.trim()), s && (e = zs(e)), e;
}
const No = {
  created(e, { modifiers: { lazy: t, trim: s, number: n } }, r) {
    e[Ps] = qn(r);
    const i = n || r.props && r.props.type === "number";
    ct(e, t ? "change" : "input", (l) => {
      l.target.composing || e[Ps](zn(e.value, s, i));
    }), (s || i) && ct(e, "change", () => {
      e.value = zn(e.value, s, i);
    }), t || (ct(e, "compositionstart", Do), ct(e, "compositionend", Jn), ct(e, "change", Jn));
  },
  // set value on mounted so it's after min/max for type="range"
  mounted(e, { value: t }) {
    e.value = t ?? "";
  },
  beforeUpdate(e, { value: t, oldValue: s, modifiers: { lazy: n, trim: r, number: i } }, l) {
    if (e[Ps] = qn(l), e.composing) return;
    const o = (i || e.type === "number") && !/^0\d/.test(e.value) ? zs(e.value) : e.value, c = t ?? "";
    if (o === c)
      return;
    const d = e.getRootNode();
    (d instanceof Document || d instanceof ShadowRoot) && d.activeElement === e && e.type !== "range" && (n && t === s || r && e.value.trim() === c) || (e.value = c);
  }
}, jo = /* @__PURE__ */ le({ patchProp: Io }, mo);
let Yn;
function Ho() {
  return Yn || (Yn = ql(jo));
}
const ko = ((...e) => {
  const t = Ho().createApp(...e), { mount: s } = t;
  return t.mount = (n) => {
    const r = Vo(n);
    if (!r) return;
    const i = t._component;
    !R(i) && !i.render && !i.template && (i.template = r.innerHTML), r.nodeType === 1 && (r.textContent = "");
    const l = s(r, !1, Lo(r));
    return r instanceof Element && (r.removeAttribute("v-cloak"), r.setAttribute("data-v-app", "")), l;
  }, t;
});
function Lo(e) {
  if (e instanceof SVGElement)
    return "svg";
  if (typeof MathMLElement == "function" && e instanceof MathMLElement)
    return "mathml";
}
function Vo(e) {
  return z(e) ? document.querySelector(e) : e;
}
let ri = "/api/v1", Vs = null, ii = 15e3;
function Uo({ baseUrl: e, token: t, timeout: s }) {
  ri = e.replace(/\/+$/, ""), t && (Vs = t), s && (ii = s);
}
async function Xn(e) {
  const t = `${ri}${e}`, s = { Accept: "application/json" };
  Vs && (s.Authorization = `Bearer ${Vs}`);
  const n = new AbortController(), r = setTimeout(() => n.abort(), ii);
  try {
    const i = await fetch(t, { headers: s, signal: n.signal });
    if (!i.ok) throw new Error(`HTTP ${i.status}`);
    return await i.json();
  } finally {
    clearTimeout(r);
  }
}
const Zn = {
  getConfig: (e) => Xn(`/portal/${e}/config`),
  getFilterValues: (e) => Xn(`/portal/${e}/filter-values`)
};
function Wo() {
  const e = /* @__PURE__ */ fs({
    config: null,
    filterValues: [],
    selections: {},
    catalogTemplateSlug: "",
    catalogUrl: "/catalog-embed",
    locale: "de",
    loading: !1,
    error: null
  }), t = {
    branding: ue(() => {
      var n;
      return ((n = e.config) == null ? void 0 : n.branding) || {};
    }),
    filterSteps: ue(() => {
      var n;
      return ((n = e.config) == null ? void 0 : n.filter_steps) || [];
    }),
    isComplete: ue(() => {
      var r;
      const n = ((r = e.config) == null ? void 0 : r.filter_steps) || [];
      return n.length > 0 && n.every((i) => !!e.selections[i.key]);
    }),
    redirectUrl: ue(() => {
      var i;
      if (!e.catalogTemplateSlug) return null;
      const n = new URLSearchParams(), r = ((i = e.config) == null ? void 0 : i.filter_steps) || [];
      for (const l of r) {
        const o = e.selections[l.key];
        if (o && n.set(`filters[${l.attribute_id}]`, o), l.derive_locale && o) {
          const c = Bo(o);
          c && n.set("lang", c);
        }
      }
      return e.locale && n.set("lang", n.get("lang") || e.locale), `${e.catalogUrl}/${e.catalogTemplateSlug}?${n.toString()}`;
    })
  };
  return { state: e, getters: t, actions: {
    async fetchConfig(n) {
      e.loading = !0, e.error = null;
      try {
        const r = await Zn.getConfig(n);
        e.config = r.data, e.catalogTemplateSlug = r.data.catalog_template_slug || "", e.locale = r.data.default_locale || "de";
      } catch (r) {
        e.error = r.message, console.error("[PortalEmbed] Config laden fehlgeschlagen:", r.message);
      } finally {
        e.loading = !1;
      }
    },
    async fetchFilterValues(n) {
      try {
        const r = await Zn.getFilterValues(n);
        e.filterValues = r.data || [];
      } catch (r) {
        console.error("[PortalEmbed] Filter-Werte laden fehlgeschlagen:", r.message);
      }
    },
    setSelection(n, r) {
      e.selections[n] = r;
    },
    clearSelection(n) {
      delete e.selections[n];
    },
    submit() {
      const n = t.redirectUrl.value;
      n && (window.location.href = n);
    }
  } };
}
let Os = null;
function Be() {
  return Os || (Os = Wo()), Os;
}
function Bo(e) {
  return {
    DE: "de",
    AT: "de",
    CH: "de",
    US: "en",
    GB: "en",
    AU: "en",
    CA: "en",
    IN: "en",
    SG: "en",
    FR: "fr",
    BE: "fr",
    ES: "es",
    MX: "es",
    IT: "it",
    NL: "nl",
    PL: "pl",
    JP: "ja",
    CN: "zh",
    TW: "zh",
    KR: "ko",
    BR: "pt",
    PT: "pt",
    RU: "ru",
    SE: "sv",
    NO: "no",
    DK: "da",
    FI: "fi",
    CZ: "cs",
    HU: "hu",
    HR: "hr",
    RO: "ro",
    GR: "el",
    TR: "tr"
  }[e == null ? void 0 : e.toUpperCase()] || null;
}
const Ko = {
  key: 0,
  class: "pe-loading"
}, Go = {
  key: 1,
  class: "pe-error"
}, qo = {
  key: 2,
  class: "pe-country"
}, Jo = { class: "pe-country__title" }, zo = { class: "pe-country__search" }, Yo = { class: "pe-country__list" }, Xo = ["onClick"], Zo = { class: "pe-country__flag" }, Qo = { class: "pe-country__name" }, ec = { class: "pe-country__count" }, li = {
  __name: "CountrySelectWidget",
  setup(e) {
    const { state: t, actions: s } = Be(), n = /* @__PURE__ */ Bi(""), r = ue(
      () => t.filterValues.find((d) => d.widget === "country-select") || t.filterValues[0]
    ), i = ue(() => {
      var p;
      const d = ((p = r.value) == null ? void 0 : p.values) || [], u = n.value.toLowerCase();
      return u ? d.filter(
        (w) => w.value.toLowerCase().includes(u) || w.label.toLowerCase().includes(u)
      ) : d;
    }), l = ue(() => {
      var u;
      const d = (u = r.value) == null ? void 0 : u.key;
      return d ? t.selections[d] : null;
    });
    function o(d) {
      return !d || d.length !== 2 ? "" : String.fromCodePoint(
        ...[...d.toUpperCase()].map((u) => 127462 + u.charCodeAt(0) - 65)
      );
    }
    function c(d) {
      var p;
      const u = (p = r.value) == null ? void 0 : p.key;
      u && s.setSelection(u, d);
    }
    return (d, u) => Q(t).loading ? (j(), L("div", Ko, [
      u[1] || (u[1] = N("div", {
        class: "pe-skeleton",
        style: { height: "32px", "margin-bottom": "12px" }
      }, null, -1)),
      u[2] || (u[2] = N("div", {
        class: "pe-skeleton",
        style: { height: "36px", "margin-bottom": "12px" }
      }, null, -1)),
      (j(), L(ne, null, nt(4, (p) => N("div", {
        class: "pe-skeleton",
        key: p,
        style: { height: "48px", "margin-bottom": "6px" }
      })), 64))
    ])) : Q(t).error ? (j(), L("div", Go, ee(Q(t).error), 1)) : r.value ? (j(), L("div", qo, [
      N("h3", Jo, ee(r.value.label), 1),
      N("div", zo, [
        nl(N("input", {
          "onUpdate:modelValue": u[0] || (u[0] = (p) => n.value = p),
          type: "text",
          placeholder: "Land suchen...",
          class: "pe-country__input"
        }, null, 512), [
          [No, n.value]
        ])
      ]),
      N("div", Yo, [
        (j(!0), L(ne, null, nt(i.value, (p) => (j(), L("button", {
          key: p.value,
          class: it(["pe-country__item", { "pe-country__item--active": l.value === p.value }]),
          onClick: (w) => c(p.value)
        }, [
          N("span", Zo, ee(o(p.value)), 1),
          N("span", Qo, ee(p.label), 1),
          N("span", ec, ee(p.count), 1)
        ], 10, Xo))), 128))
      ])
    ])) : Re("", !0);
  }
}, tc = {
  key: 0,
  class: "pe-lang"
}, sc = { class: "pe-lang__title" }, nc = { class: "pe-lang__list" }, rc = ["onClick"], oi = {
  __name: "LanguageSelectWidget",
  setup(e) {
    const { state: t, actions: s } = Be(), n = {
      de: "Deutsch",
      en: "English",
      fr: "Français",
      es: "Español",
      it: "Italiano",
      nl: "Nederlands",
      pl: "Polski",
      pt: "Português",
      ru: "Русский",
      zh: "中文",
      ja: "日本語",
      ko: "한국어",
      cs: "Čeština",
      hu: "Magyar",
      hr: "Hrvatski",
      da: "Dansk",
      sv: "Svenska",
      no: "Norsk",
      fi: "Suomi",
      el: "Ελληνικά",
      tr: "Türkçe",
      ro: "Română",
      ar: "العربية"
    }, r = ue(
      () => t.filterValues.find((o) => o.widget === "language-select")
    ), i = ue(() => {
      var c;
      const o = (c = r.value) == null ? void 0 : c.key;
      return o ? t.selections[o] : null;
    });
    function l(o) {
      var d;
      const c = (d = r.value) == null ? void 0 : d.key;
      c && s.setSelection(c, o);
    }
    return (o, c) => r.value ? (j(), L("div", tc, [
      N("h3", sc, ee(r.value.label), 1),
      N("div", nc, [
        (j(!0), L(ne, null, nt(r.value.values, (d) => (j(), L("button", {
          key: d.value,
          class: it(["pe-lang__badge", { "pe-lang__badge--active": i.value === d.value }]),
          onClick: (u) => l(d.value)
        }, ee(n[d.value] || d.label), 11, rc))), 128))
      ])
    ])) : Re("", !0);
  }
}, ic = {
  key: 0,
  class: "pe-dropdown"
}, lc = { class: "pe-dropdown__label" }, oc = ["value"], cc = ["value"], Us = {
  __name: "FilterDropdownWidget",
  props: {
    attribute: { type: String, default: null }
  },
  setup(e) {
    const t = e, { state: s, actions: n } = Be(), r = ue(() => t.attribute ? s.filterValues.find((o) => o.attribute_id === t.attribute) : s.filterValues.find((o) => o.widget === "filter-dropdown")), i = ue(() => {
      var c;
      const o = (c = r.value) == null ? void 0 : c.key;
      return o ? s.selections[o] : null;
    });
    function l(o) {
      var d;
      const c = (d = r.value) == null ? void 0 : d.key;
      if (c) {
        const u = o.target.value;
        u ? n.setSelection(c, u) : n.clearSelection(c);
      }
    }
    return (o, c) => r.value ? (j(), L("div", ic, [
      N("label", lc, ee(r.value.label), 1),
      N("select", {
        class: "pe-dropdown__select",
        value: i.value || "",
        onChange: l
      }, [
        c[0] || (c[0] = N("option", { value: "" }, "— Bitte waehlen —", -1)),
        (j(!0), L(ne, null, nt(r.value.values, (d) => (j(), L("option", {
          key: d.value,
          value: d.value
        }, ee(d.label) + " (" + ee(d.count) + ") ", 9, cc))), 128))
      ], 40, oc)
    ])) : Re("", !0);
  }
}, fc = {
  key: 0,
  class: "pe-cards"
}, uc = { class: "pe-cards__title" }, ac = { class: "pe-cards__grid" }, dc = ["onClick"], hc = { class: "pe-cards__label" }, pc = { class: "pe-cards__count" }, ci = {
  __name: "FilterCardsWidget",
  props: {
    attribute: { type: String, default: null }
  },
  setup(e) {
    const t = e, { state: s, actions: n } = Be(), r = ue(() => t.attribute ? s.filterValues.find((o) => o.attribute_id === t.attribute) : s.filterValues.find((o) => o.widget === "filter-cards")), i = ue(() => {
      var c;
      const o = (c = r.value) == null ? void 0 : c.key;
      return o ? s.selections[o] : null;
    });
    function l(o) {
      var d;
      const c = (d = r.value) == null ? void 0 : d.key;
      c && n.setSelection(c, o);
    }
    return (o, c) => r.value ? (j(), L("div", fc, [
      N("h3", uc, ee(r.value.label), 1),
      N("div", ac, [
        (j(!0), L(ne, null, nt(r.value.values, (d) => (j(), L("button", {
          key: d.value,
          class: it(["pe-cards__card", { "pe-cards__card--active": i.value === d.value }]),
          onClick: (u) => l(d.value)
        }, [
          N("span", hc, ee(d.label), 1),
          N("span", pc, ee(d.count) + " Produkte", 1)
        ], 10, dc))), 128))
      ])
    ])) : Re("", !0);
  }
}, gc = {
  key: 0,
  class: "pe-filter-steps"
}, _c = {
  key: 1,
  class: "pe-filter-steps"
}, mc = {
  __name: "FilterStepsWidget",
  setup(e) {
    const t = {
      "country-select": li,
      "language-select": oi,
      "filter-dropdown": Us,
      "filter-cards": ci
    }, { state: s } = Be(), n = ue(
      () => (s.filterValues || []).map((r) => ({
        ...r,
        component: t[r.widget] || Us
      }))
    );
    return (r, i) => Q(s).loading ? (j(), L("div", gc, [...i[0] || (i[0] = [
      N("div", {
        class: "pe-skeleton",
        style: { height: "48px", "margin-bottom": "12px" }
      }, null, -1),
      N("div", {
        class: "pe-skeleton",
        style: { height: "48px", "margin-bottom": "12px" }
      }, null, -1)
    ])])) : (j(), L("div", _c, [
      (j(!0), L(ne, null, nt(n.value, (l) => (j(), Qr(Cl(l.component), {
        key: l.attribute_id || l.key,
        attribute: l.attribute_id,
        class: "pe-filter-steps__item"
      }, null, 8, ["attribute"]))), 128))
    ]));
  }
}, bc = ["disabled"], vc = {
  key: 0,
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  "stroke-width": "2",
  "stroke-linecap": "round",
  "stroke-linejoin": "round"
}, yc = {
  __name: "SubmitButtonWidget",
  props: {
    label: { type: String, default: null }
  },
  setup(e) {
    const t = e, { state: s, getters: n, actions: r } = Be();
    function i() {
      r.submit();
    }
    return (l, o) => (j(), L("button", {
      class: it(["pe-submit", { "pe-submit--disabled": !Q(n).isComplete.value }]),
      disabled: !Q(n).isComplete.value,
      onClick: i
    }, [
      un(ee(Q(n).isComplete.value ? t.label || "Weiter" : "Bitte Auswahl treffen") + " ", 1),
      Q(n).isComplete.value ? (j(), L("svg", vc, [...o[0] || (o[0] = [
        N("polyline", { points: "9 18 15 12 9 6" }, null, -1)
      ])])) : Re("", !0)
    ], 10, bc));
  }
}, xc = {
  key: 0,
  class: "pe-loading"
}, Sc = {
  key: 1,
  class: "pe-branding"
}, wc = {
  key: 0,
  class: "pe-branding__subtitle"
}, Cc = {
  key: 1,
  class: "pe-branding__title"
}, Tc = {
  key: 2,
  class: "pe-branding__desc"
}, Ec = {
  key: 3,
  class: "pe-branding__features"
}, Ac = {
  __name: "BrandingWidget",
  setup(e) {
    const { state: t, getters: s } = Be();
    return (n, r) => {
      var i;
      return Q(t).loading ? (j(), L("div", xc, [...r[0] || (r[0] = [
        N("div", {
          class: "pe-skeleton",
          style: { height: "16px", width: "200px", "margin-bottom": "12px" }
        }, null, -1),
        N("div", {
          class: "pe-skeleton",
          style: { height: "36px", width: "80%", "margin-bottom": "12px" }
        }, null, -1),
        N("div", {
          class: "pe-skeleton",
          style: { height: "60px", "margin-bottom": "24px" }
        }, null, -1)
      ])])) : Q(s).branding.value ? (j(), L("div", Sc, [
        Q(s).branding.value.subtitle ? (j(), L("p", wc, ee(Q(s).branding.value.subtitle), 1)) : Re("", !0),
        Q(s).branding.value.title ? (j(), L("h1", Cc, ee(Q(s).branding.value.title), 1)) : Re("", !0),
        Q(s).branding.value.hero_text ? (j(), L("p", Tc, ee(Q(s).branding.value.hero_text), 1)) : Re("", !0),
        (i = Q(s).branding.value.features) != null && i.length ? (j(), L("ul", Ec, [
          (j(!0), L(ne, null, nt(Q(s).branding.value.features, (l, o) => (j(), L("li", { key: o }, [
            r[1] || (r[1] = N("span", { class: "pe-branding__check" }, [
              N("svg", {
                viewBox: "0 0 24 24",
                fill: "none",
                stroke: "#fff",
                "stroke-width": "2.5",
                "stroke-linecap": "round",
                "stroke-linejoin": "round"
              }, [
                N("polyline", { points: "20 6 9 17 4 12" })
              ])
            ], -1)),
            un(" " + ee(l), 1)
          ]))), 128))
        ])) : Re("", !0)
      ])) : Re("", !0);
    };
  }
}, Ws = {
  "country-select": li,
  "language-select": oi,
  "filter-dropdown": Us,
  "filter-cards": ci,
  "filter-steps": mc,
  "submit-button": yc,
  branding: Ac
}, Bs = [];
function Ks() {
  document.querySelectorAll("[data-portal]").forEach((t) => {
    if (t.__pe_mounted) return;
    const s = t.getAttribute("data-portal"), n = Ws[s];
    if (!n) {
      console.warn(`[PortalEmbed] Unbekanntes Widget: "${s}". Verfuegbar: ${Object.keys(Ws).join(", ")}`);
      return;
    }
    const r = {};
    for (const l of t.attributes)
      if (l.name.startsWith("data-") && l.name !== "data-portal") {
        const o = l.name.slice(5).replace(/-([a-z])/g, (c, d) => d.toUpperCase());
        r[o] = l.value;
      }
    const i = ko({
      render() {
        return ho(n, r);
      }
    });
    i.mount(t), t.__pe_mounted = !0, Bs.push({ el: t, app: i });
  });
}
function Pc() {
  Bs.forEach(({ app: e }) => e.unmount()), Bs.length = 0;
}
async function Oc(e = {}) {
  Uo({
    baseUrl: e.api || "/api/v1",
    token: e.token,
    timeout: e.timeout
  });
  const { state: t, actions: s } = Be();
  e.catalogUrl && (t.catalogUrl = e.catalogUrl);
  const n = e.slug;
  if (!n) {
    console.error("[PortalEmbed] Kein slug angegeben in init()");
    return;
  }
  await Promise.all([
    s.fetchConfig(n),
    s.fetchFilterValues(n)
  ]), document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", Ks) : Ks();
}
const Mc = {
  init: Oc,
  mount: Ks,
  destroy: Pc,
  store: Be,
  widgets: Ws,
  version: "1.0.0"
};
typeof window < "u" && (window.PortalEmbed = Mc);
export {
  Mc as default,
  Pc as destroy,
  Oc as init,
  Ks as mount,
  Be as useStore
};
