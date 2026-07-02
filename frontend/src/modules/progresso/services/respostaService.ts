import api from '../../../core/services/api'; // Ajuste a quantidade de "../" para chegar na pasta src/core/services/api

export interface CadastroRespostaPayload {
  usuario_id: number;
  tipo: 'flashcard' | 'quiz';
  referencia_id: number;
  correto: boolean;
}

export const respostaService = {
  async registrarResposta(payload: CadastroRespostaPayload): Promise<void> {
    // Usando a instância exatamente como o Dashboard usa
    await api({
      method: 'POST',
      url: '/respostas',
      data: payload
    });
  }
};