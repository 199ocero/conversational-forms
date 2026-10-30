import { describe, expect, it } from 'vitest'
import { arcPath, assignLanes } from '../../resources/js/support/arcs.js'

describe('logic map arcs', () => {
    it('puts arcs whose rows overlap in separate lanes', () => {
        // A long arc over two short ones that don't overlap each other.
        expect(
            assignLanes([
                { from: 0, to: 300 },
                { from: 0, to: 100 },
                { from: 150, to: 250 },
            ]),
        ).toEqual([1, 0, 0])
    })

    it('keeps arcs that only touch in the same lane', () => {
        expect(
            assignLanes([
                { from: 0, to: 100 },
                { from: 100, to: 200 },
            ]),
        ).toEqual([0, 0])
    })

    it('stacks arcs over the same rows outwards', () => {
        expect(
            assignLanes([
                { from: 0, to: 100 },
                { from: 0, to: 100 },
                { from: 0, to: 100 },
            ]),
        ).toEqual([0, 1, 2])
    })

    it('draws a curve that bows out to the right', () => {
        expect(arcPath(10, 20, 80, 30)).toBe('M 10 20 C 40 20, 40 80, 10 80')
    })
})
