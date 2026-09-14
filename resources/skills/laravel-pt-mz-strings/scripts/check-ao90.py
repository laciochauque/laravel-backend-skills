#!/usr/bin/env python3
"""
Detecta ortografia AO90 em ficheiros que deviam estar em pré-AO90.

Uso:  python3 check-ao90.py lang/pt_PT app
Devolve código de saída 1 se encontrar ocorrências — serve para o CI.
"""
import re
import sys
import glob
import os

PADROES = {
    r'\bação\b': 'acção',
    r'\bações\b': 'acções',
    r'\bativ(o|a|os|as|ar|ado|ada)\b': 'activ…',
    r'\batua(l|is|lizar|lização|lizado)\b': 'actua…',
    r'\bcorret(o|a|os|as)\b': 'correct…',
    r'\bcorreç(ão|ões)\b': 'correcç…',
    r'\botimiz\w*': 'optimiz…',
    r'\bótim(o|a|os|as)\b': 'óptim…',
    r'\bobjetiv\w*': 'objectiv…',
    r'\bdiretóri\w*': 'directóri…',
    r'\bdiretriz\w*': 'directriz…',
    r'\bexceç(ão|ões)\b': 'excepç…',
    r'\bseleç(ão|ões)\b': 'selecç…',
    r'\bselecionad\w*': 'seleccionad…',
    r'\bcoleç(ão|ões)\b': 'colecç…',
    r'\barquitetura\b': 'arquitectura',
    r'\bprojet(o|os)\b': 'project…',
    r'\beletrónic\w*': 'electrónic…',
    r'\badoção\b': 'adopção',
    r'\baspet(o|os)\b': 'aspect…',
    r'\bdeteç(ão|ões)\b': 'detecç…',
    r'\bfato\b': 'facto (se for acontecimento)',
}

EXTENSOES = ('.php', '.md', '.json', '.blade.php')


def verificar(caminhos):
    total = 0
    for caminho in caminhos:
        alvos = (
            [f for e in EXTENSOES
             for f in glob.glob(os.path.join(caminho, '**', '*' + e), recursive=True)]
            if os.path.isdir(caminho) else [caminho]
        )
        for ficheiro in sorted(set(alvos)):
            try:
                linhas = open(ficheiro, encoding='utf-8').read().splitlines()
            except (UnicodeDecodeError, OSError):
                continue
            for n, linha in enumerate(linhas, 1):
                for padrao, correccao in PADROES.items():
                    for achado in set(re.findall(padrao, linha.lower())):
                        termo = achado if isinstance(achado, str) else linha.lower()
                        print(f'{ficheiro}:{n}: "{termo}" → {correccao}')
                        total += 1
    return total


if __name__ == '__main__':
    caminhos = sys.argv[1:] or ['lang', 'app']
    n = verificar(caminhos)
    print(f'\n{n} ocorrência(s) de ortografia AO90.' if n else '\nOrtografia pré-AO90 ✔')
    sys.exit(1 if n else 0)
