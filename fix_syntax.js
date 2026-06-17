const fs = require('fs');
let code = fs.readFileSync('frontend/src/App.tsx', 'utf8');

// The block causing the issue is:
/*
                          </button>
                        </form>
                      </div>

                      
                        
                      </div>

                    </div>

                  </div>
*/

// I will just find the exact block around the Save Keyword Route button and replace it
const target = `
                          <button
                            type="submit"
                            className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-xl transition-all flex items-center justify-center gap-1.5 hover:shadow text-xs uppercase tracking-wider"
                          >
                            <Plus className="w-4 h-4" /> Save Keyword Route
                          </button>
                        </form>
                      </div>

                      
                        
                      </div>

                    </div>

                  </div>
`;

const replacement = `
                          <button
                            type="submit"
                            className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-xl transition-all flex items-center justify-center gap-1.5 hover:shadow text-xs uppercase tracking-wider"
                          >
                            <Plus className="w-4 h-4" /> Save Keyword Route
                          </button>
                        </form>
                      </div>

                    </div>

                  </div>
`;

code = code.replace(target, replacement);

fs.writeFileSync('frontend/src/App.tsx', code);
console.log('Fixed syntax error in App.tsx');
