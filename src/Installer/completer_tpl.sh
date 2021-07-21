_{{ name }}_completion()
{
    local cmd cur prev
    local IFS=$'\t\n'
    compopt -o nospace
    cur="${COMP_WORDS[COMP_CWORD]}"
    prev="${COMP_WORDS[COMP_CWORD-1]}"

    if [ "$cur" == "=" ];
    then
      cur=""
    fi
    if [ "$cur" == ":" ];
    then
      cur=""
    fi
    cmd=$({{ executable }} "${COMP_CWORD}" "${COMP_WORDS[@]}")
    if [ $? == 212 ]; then
      cur=${cur//\\ / }
      [[ ${cur} == "~/"* ]] && cur=${cur/\~/$HOME}
      compopt -o filenames
      local files=("${cur}"*)
      [[ -e ${files[0]} ]] && COMPREPLY=( "${files[@]// /\ }" )
      return 0
    fi
    COMPREPLY=( $(compgen -W "${cmd}" -- ${cur}) )
    return 0
}

complete -F _{{ name }}_completion {{ name }}
