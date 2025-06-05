import os
from pathlib import Path
import fnmatch

def load_gitignore_patterns(gitignore_path):
    patterns = []
    if not os.path.exists(gitignore_path):
        return patterns
    with open(gitignore_path, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if line == '' or line.startswith('#'):
                continue
            patterns.append(line)
    return patterns

def is_ignored(path, patterns, root_dir):
    try:
        rel_path = os.path.relpath(path, root_dir).replace("\\", "/")
    except ValueError:
        return False
    
    if rel_path == '.':
        return False
        
    ignored = False
    
    for pattern in patterns:
        if pattern == '':
            continue
            
        negate = False
        current_pattern = pattern
        if current_pattern.startswith('!'):
            negate = True
            current_pattern = current_pattern[1:]
            if current_pattern == '':
                continue
                
        dir_only = current_pattern.endswith('/')
        if dir_only:
            current_pattern = current_pattern.rstrip('/')
            if current_pattern == '':
                continue
                
        if current_pattern.startswith('/'):
            current_pattern = current_pattern[1:]
            if current_pattern == '':
                continue
                
        if '/' in current_pattern:
            match_target = rel_path
        else:
            match_target = os.path.basename(rel_path)
            
        if fnmatch.fnmatch(match_target, current_pattern):
            if dir_only and not os.path.isdir(path):
                continue
                
            if negate:
                ignored = False
            else:
                ignored = True
                
    return ignored

def write_tree(root_dir, output_file, patterns):
    with open(output_file, 'w', encoding='utf-8') as f:
        def _tree(current_dir, prefix=""):
            try:
                entries = sorted(os.listdir(current_dir))
            except PermissionError:
                f.write(prefix + "└── [Permission Denied]\n")
                return
                
            filtered = []
            for entry in entries:
                full_path = os.path.join(current_dir, entry)
                if not is_ignored(full_path, patterns, root_dir):
                    filtered.append(entry)
                    
            entries_count = len(filtered)

            for idx, entry in enumerate(filtered):
                path = os.path.join(current_dir, entry)
                connector = "└── " if idx == entries_count - 1 else "├── "
                f.write(prefix + connector + entry + "\n")

                if os.path.isdir(path):
                    extension = "    " if idx == entries_count - 1 else "│   "
                    _tree(path, prefix + extension)

        f.write(os.path.basename(root_dir) + "/\n")
        _tree(root_dir)

if __name__ == "__main__":
    root_directory = os.getcwd()  # Use current working directory
    gitignore_path = os.path.join(root_directory, '.gitignore')
    output_txt_file = 'tree_output.txt'

    patterns = load_gitignore_patterns(gitignore_path)
    write_tree(root_directory, output_txt_file, patterns)

    print(f"Directory tree has been written to {output_txt_file}")
    print(f"Used {len(patterns)} .gitignore patterns")