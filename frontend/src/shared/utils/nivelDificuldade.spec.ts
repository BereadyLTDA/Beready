import { describe, expect, it } from 'vitest'
import { getNivelLabelKey, getNivelVariant, normalizeNivel } from './nivelDificuldade'

describe('normalizeNivel', () => {
  it('mantém os níveis atuais, sem diferenciar maiúsculas', () => {
    expect(normalizeNivel('iniciante')).toBe('iniciante')
    expect(normalizeNivel('Intermediario')).toBe('intermediario')
    expect(normalizeNivel('AVANCADO')).toBe('avancado')
  })

  it('converte os valores antigos do formulário de flashcards', () => {
    expect(normalizeNivel('facil')).toBe('iniciante')
    expect(normalizeNivel('medio')).toBe('intermediario')
    expect(normalizeNivel('dificil')).toBe('avancado')
  })

  it('cai em iniciante quando o valor é vazio ou desconhecido', () => {
    expect(normalizeNivel(undefined)).toBe('iniciante')
    expect(normalizeNivel(null)).toBe('iniciante')
    expect(normalizeNivel('')).toBe('iniciante')
    expect(normalizeNivel('expert')).toBe('iniciante')
  })
})

describe('rótulo e cor do nível', () => {
  it('usa a variante do nível já normalizado', () => {
    expect(getNivelVariant('iniciante')).toBe('success')
    expect(getNivelVariant('medio')).toBe('warning')
    expect(getNivelVariant('avancado')).toBe('danger')
    expect(getNivelVariant(null)).toBe('success')
  })

  it('monta a chave i18n em common.*', () => {
    expect(getNivelLabelKey('dificil')).toBe('common.avancado')
    expect(getNivelLabelKey(undefined)).toBe('common.iniciante')
  })
})
